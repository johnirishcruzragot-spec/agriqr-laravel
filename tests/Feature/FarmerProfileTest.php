<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmerParcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FarmerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_displays_rsbsa_details_farmer_types_and_parcels(): void
    {
        $farmer = Farmer::factory()->create([
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'address' => 'San Roque, Baliuag, Bulacan',
        ]);
        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-PROFILE-100',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'date_encoded' => '2026-03-10',
            'ffrs_generated_number' => 'FFRS-100',
            'gender' => 'Female',
            'birthdate' => '1987-04-15',
            'is_farmer' => true,
            'is_farmworker' => false,
            'is_fisherfolk' => null,
            'farmer_address_1' => 'San Roque',
            'farmer_address_2' => 'Baliuag',
        ]);
        FarmerParcel::factory()->create([
            'farmer_id' => $farmer->id,
            'parcel_number' => 'PARCEL-10',
            'commodity_name' => 'Rice',
            'parcel_area' => 2.5,
            'owner_name' => 'Maya Reyes',
        ]);
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee('RSBSA-PROFILE-100')
            ->assertSee('FFRS-100')
            ->assertSee('15, 1987')
            ->assertSee('Indigenous people (IP)')
            ->assertSee('Not provided')
            ->assertSee('PARCEL-10')
            ->assertSee('Rice')
            ->assertSee('Maya Reyes')
            ->assertSee('Department of Agriculture')
            ->assertSee('PARCEL ADDRESS 3');
    }

    public function test_profile_shows_safe_placeholders_when_rsbsa_details_and_photo_are_missing(): void
    {
        $farmer = Farmer::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee('Not registered')
            ->assertSee('No farmer photo uploaded')
            ->assertSee('No parcel information');
    }

    public function test_farmer_can_upload_a_photo_that_is_served_only_through_the_authenticated_profile_route(): void
    {
        Storage::fake('local');
        $farmer = Farmer::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(route('farmers.photo.update', $farmer), [
            'photo' => UploadedFile::fake()->createWithContent(
                'farmer.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lNEAAAAASUVORK5CYII='),
            ),
        ]);

        $response->assertRedirect(route('farmers.show', $farmer))
            ->assertSessionHasNoErrors();

        $farmer->refresh();
        $this->assertNotNull($farmer->photo_path);
        Storage::disk('local')->assertExists($farmer->photo_path);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'FARMER',
            'record_id' => $farmer->id,
            'action' => 'UPDATE',
        ]);

        $this->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee(route('farmers.photo', $farmer));

        $this->get(route('farmers.photo', $farmer))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_unsupported_farmer_photo_upload_is_rejected_without_changing_the_profile(): void
    {
        Storage::fake('local');
        $farmer = Farmer::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        $svg = UploadedFile::fake()->createWithContent('farmer.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->actingAs($user)
            ->from(route('farmers.show', $farmer))
            ->post(route('farmers.photo.update', $farmer), ['photo' => $svg])
            ->assertRedirect(route('farmers.show', $farmer))
            ->assertSessionHasErrors('photo');

        $this->assertNull($farmer->fresh()->photo_path);
        $this->assertDatabaseCount('audit_logs', 0);
        Storage::disk('local')->assertDirectoryEmpty('farmer-photos');
    }

    public function test_farmer_profile_escapes_untrusted_names_and_parcel_text(): void
    {
        $farmer = Farmer::factory()->create([
            'first_name' => '<script>alert(1)</script>',
            'last_name' => 'Farmer',
        ]);
        FarmerParcel::factory()->create([
            'farmer_id' => $farmer->id,
            'parcel_number' => 'P-<script>',
            'commodity_name' => '<img src=x onerror=alert(1)>',
        ]);
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->get(route('farmers.show', $farmer));

        $response->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_farmer_photo_route_requires_an_authorized_role(): void
    {
        $farmer = Farmer::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'custodian']))
            ->get(route('farmers.photo', $farmer))
            ->assertForbidden();
    }
}

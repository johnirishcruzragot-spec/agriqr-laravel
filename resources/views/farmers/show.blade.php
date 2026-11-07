@extends('layouts.app')

@section('title', 'Farmer profile')
@section('section', 'Farmers')

@section('content')
    @php
        $rsbsa = $farmer->rsbsaRecord;
        $farmerTypes = [
            'Farmer' => $rsbsa?->is_farmer,
            'Farmworker' => $rsbsa?->is_farmworker,
            'Fisherfolk' => $rsbsa?->is_fisherfolk,
            'Agribusiness' => $rsbsa?->is_agribusiness,
            'Indigenous people (IP)' => $rsbsa?->is_ip,
            'Organic practitioner' => $rsbsa?->is_organic_practitioner,
            'Agrarian reform beneficiary (ARB)' => $rsbsa?->is_arb,
        ];
        $address = collect([$farmer->barangay, $farmer->municipality, $farmer->province])
            ->filter()
            ->implode(', ');
    @endphp

    <div class="page-heading heading-with-action">
        <div>
            @if (auth()->user()->canUseButton('farmers.list'))<a class="back-link" href="{{ route('farmers.index') }}">← Farmer directory</a>@endif
            <span class="eyebrow">RSBSA FARMER RECORD · {{ $farmer->farmer_id }}</span>
            <h1>{{ $farmer->full_name }}</h1>
            <p>{{ $address ?: 'Location not provided' }}</p>
        </div>
        <div class="heading-actions no-print">
            @if (auth()->user()->canUseButton('farmers.edit'))
                <a class="button button-secondary" href="{{ route('farmers.edit', $farmer) }}">Edit profile</a>
            @endif
            @if (auth()->user()->canUseButton('farmers.print'))<button class="button button-primary" type="button" onclick="window.print()">Print record</button>@endif
        </div>
    </div>

    <section class="panel rsbsa-hero">
        <div class="rsbsa-photo-column">
            @if ($farmer->photo_path)
                <img class="rsbsa-photo" src="{{ route('farmers.photo', $farmer) }}" alt="Photo of {{ $farmer->full_name }}">
            @else
                <div class="rsbsa-photo rsbsa-photo-placeholder" aria-label="No farmer photo uploaded">
                    {{ strtoupper(substr($farmer->first_name, 0, 1).substr($farmer->last_name, 0, 1)) }}
                </div>
            @endif
            @if (auth()->user()->canUseButton('farmers.edit'))
                <form method="post" action="{{ route('farmers.photo.update', $farmer) }}" enctype="multipart/form-data" class="farmer-photo-form no-print">
                    @csrf
                    <label class="field">
                        <span>{{ $farmer->photo_path ? 'Replace farmer photo' : 'Add farmer photo' }}</span>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                        <small>JPG, PNG or WebP. Maximum 3 MB.</small>
                    </label>
                    <button class="button button-secondary button-wide" type="submit">Save photo</button>
                </form>
            @endif
        </div>
        <div class="rsbsa-hero-details">
            <div class="rsbsa-hero-heading">
                <div>
                    <span class="eyebrow">REGISTERED FARMER</span>
                    <h2>{{ $farmer->full_name }}</h2>
                    <p>{{ $address ?: 'Farmer address not provided' }}</p>
                </div>
                <span class="status {{ $farmer->status === 'active' ? 'status-green' : ($farmer->status === 'deceased' ? 'status-amber' : 'status-neutral') }}">
                    {{ str($farmer->status)->title() }}
                </span>
            </div>
            <div class="rsbsa-identity-grid">
                <div><span class="detail-label">LGU RSBSA NUMBER</span><strong>{{ $rsbsa?->rsbsa_number ?: 'Not registered' }}</strong></div>
                <div><span class="detail-label">FFRS SYSTEM GENERATED NO.</span><strong>{{ $rsbsa?->ffrs_generated_number ?: 'Not provided' }}</strong></div>
                <div><span class="detail-label">DATE ENCODED</span><strong>{{ $rsbsa?->date_encoded?->format('M j, Y') ?? 'Not provided' }}</strong></div>
            </div>
        </div>
    </section>

    <div class="rsbsa-profile-layout section-gap">
        <div class="rsbsa-main-column">
            <section class="panel rsbsa-section">
                <div class="panel-heading"><div><h2>Personal information</h2><p>Identity details from the RSBSA record</p></div></div>
                <div class="detail-grid">
                    <div><span class="detail-label">LAST NAME</span><strong>{{ $farmer->last_name }}</strong></div>
                    <div><span class="detail-label">FIRST NAME</span><strong>{{ $farmer->first_name }}</strong></div>
                    <div><span class="detail-label">MIDDLE NAME</span><strong>{{ $farmer->middle_name ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">EXT NAME</span><strong>{{ $farmer->suffix ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">GENDER</span><strong>{{ $rsbsa?->gender ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">BIRTHDATE</span><strong>{{ $rsbsa?->birthdate?->format('M j, Y') ?? 'Not provided' }}</strong></div>
                    <div><span class="detail-label">CONTACT NUMBER</span><strong>{{ $farmer->contact_number ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">AGRIQR FARMER ID</span><strong>{{ $farmer->farmer_id }}</strong></div>
                    @if ($farmer->status === 'deceased')
                        <div><span class="detail-label">DATE DECEASED</span><strong>{{ $farmer->deceased_at?->format('M j, Y') ?? 'Not provided' }}</strong></div>
                    @endif
                    @foreach ($customFields as $field)
                        <div><span class="detail-label">{{ str($field['label'])->upper() }}</span><strong>{{ $farmer->custom_fields[$field['key']] ?? 'Not provided' }}</strong></div>
                    @endforeach
                </div>
                <div class="rsbsa-subsection">
                    <span class="detail-label">FARMER CLASSIFICATION</span>
                    <div class="rsbsa-type-grid">
                        @foreach ($farmerTypes as $label => $value)
                            <div><span>{{ $label }}</span><strong>{{ $value === null ? 'Not provided' : ($value ? 'Yes' : 'No') }}</strong></div>
                        @endforeach
                        <div><span>Tribe</span><strong>{{ $rsbsa?->tribe ?: 'Not provided' }}</strong></div>
                    </div>
                </div>
            </section>

            <section class="panel rsbsa-section section-gap">
                <div class="panel-heading"><div><h2>Seasonal Hybrid / Inbred group history</h2><p>Current and previous group assignments are retained for audit and program eligibility.</p></div></div>
                @if ($farmer->seasonGroupAssignments->isEmpty())
                    <div class="empty-state compact"><strong>No seasonal group assignments</strong><p>Season and Hybrid/Inbred assignments will appear here when recorded.</p></div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>SEASON</th><th>GROUP</th><th>ASSIGNED</th><th>ENDED</th><th>ASSIGNED BY</th><th>STATUS</th></tr></thead>
                            <tbody>
                                @foreach ($farmer->seasonGroupAssignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->seasonGroup->season->name }}</td>
                                        <td><span class="status status-green">{{ str($assignment->seasonGroup->seed_type)->title() }}</span></td>
                                        <td>{{ $assignment->started_at?->format('M j, Y') ?? '—' }}</td>
                                        <td>{{ $assignment->ended_at?->format('M j, Y') ?? '—' }}</td>
                                        <td>{{ $assignment->assigner?->name ?? 'System / unavailable' }}</td>
                                        <td><span class="status {{ $assignment->ended_at ? 'status-neutral' : 'status-green' }}">{{ $assignment->ended_at ? 'Previous' : 'Current' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="panel rsbsa-section section-gap">
                <div class="panel-heading"><div><h2>Farmer address</h2><p>Address lines included in the imported RSBSA file</p></div></div>
                <div class="detail-grid">
                    <div><span class="detail-label">FARMER ADDRESS 1</span><strong>{{ $rsbsa?->farmer_address_1 ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">FARMER ADDRESS 2</span><strong>{{ $rsbsa?->farmer_address_2 ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">FARMER ADDRESS 3</span><strong>{{ $rsbsa?->farmer_address_3 ?: 'Not provided' }}</strong></div>
                    <div><span class="detail-label">PROFILE ADDRESS</span><strong>{{ $farmer->address ?: 'Not provided' }}</strong></div>
                </div>
            </section>

            <section class="panel rsbsa-section section-gap">
                <div class="panel-heading"><div><h2>Farm registration details</h2><p>Farm size and additional information from the RSBSA record</p></div></div>
                <div class="detail-grid">
                    <div><span class="detail-label">FARM SIZE</span><strong>{{ $rsbsa?->farm_size !== null ? number_format((float) $rsbsa->farm_size, 2) : 'Not provided' }}</strong></div>
                    <div><span class="detail-label">FARM SIZE UNIT</span><strong>{{ $rsbsa?->farm_size_unit ? str($rsbsa->farm_size_unit)->title() : 'Not provided' }}</strong></div>
                </div>
                <div class="rsbsa-subsection">
                    <span class="detail-label">CROP INFORMATION</span>
                    <p>{{ $rsbsa?->crop_information ?: 'Not provided' }}</p>
                </div>
                <div class="rsbsa-subsection">
                    <span class="detail-label">REMARKS</span>
                    <p>{{ $rsbsa?->remarks ?: 'Not provided' }}</p>
                </div>
            </section>

            <section class="panel rsbsa-section section-gap">
                <div class="panel-heading"><div><h2>Farm parcels and commodities</h2><p>Parcel, crop and ownership information from RSBSA</p></div><span class="table-count">{{ $farmer->parcels->count() }} parcels</span></div>
                @if ($farmer->parcels->isEmpty())
                    <div class="empty-state compact"><strong>No parcel information</strong><p>Parcel details will appear here after a CSV import.</p></div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>PARCEL NO.</th><th>PARCEL ADDRESS 1</th><th>PARCEL ADDRESS 2</th><th>PARCEL ADDRESS 3</th><th>PARCEL AREA</th><th>COMMODITY</th><th>CROP AREA</th><th>FARM TYPE</th><th>NUMBER OF HEADS</th><th>AGENCY</th><th>OWNERSHIP TYPE</th><th>OWNER NAME</th></tr></thead>
                            <tbody>
                                @foreach ($farmer->parcels as $parcel)
                                    <tr>
                                        <td>{{ $parcel->parcel_number ?: '—' }}</td>
                                        <td>{{ $parcel->parcel_address_1 ?: '—' }}</td>
                                        <td>{{ $parcel->parcel_address_2 ?: '—' }}</td>
                                        <td>{{ $parcel->parcel_address_3 ?: '—' }}</td>
                                        <td>{{ $parcel->parcel_area !== null ? number_format((float) $parcel->parcel_area, 2).' ha' : '—' }}</td>
                                        <td>{{ $parcel->commodity_name ?: '—' }}</td>
                                        <td>{{ $parcel->crop_area !== null ? number_format((float) $parcel->crop_area, 2).' ha' : '—' }}</td>
                                        <td>{{ $parcel->farm_type ?: '—' }}</td>
                                        <td>{{ $parcel->number_of_heads !== null ? number_format((float) $parcel->number_of_heads, 2) : '—' }}</td>
                                        <td>{{ $parcel->agency ?: '—' }}</td>
                                        <td>{{ $parcel->ownership_type ?: '—' }}</td>
                                        <td>{{ $parcel->owner_name ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($farmer->farm_information)
                    <div class="detail-note"><span class="detail-label">ADDITIONAL FARM NOTES</span><p>{{ $farmer->farm_information }}</p></div>
                @endif
            </section>

            @if (auth()->user()->canAccessModule('distributions'))
                <section class="panel rsbsa-section section-gap">
                <div class="panel-heading"><div><h2>Distribution history</h2><p>Assistance recorded for this farmer</p></div><span class="table-count">{{ $farmer->distributions->count() }} records</span></div>
                @if ($farmer->distributions->isEmpty())
                    <div class="empty-state compact"><strong>No distribution history</strong><p>Recorded assistance will appear here.</p></div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>TRANSACTION</th><th>PROGRAM</th><th>ITEMS</th><th>DATE</th><th>STATUS</th></tr></thead>
                            <tbody>
                                @foreach ($farmer->distributions as $distribution)
                                    <tr>
                                        <td>@if (auth()->user()->canUseButton('distributions.view'))<a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a>@else<strong>{{ $distribution->transaction_number }}</strong>@endif</td>
                                        <td>{{ $distribution->program?->program_name ?? '—' }}</td>
                                        <td>{{ $distribution->items->map(fn ($item) => $item->supply->supply_name.' × '.number_format((float) $item->quantity, 2))->join(', ') }}</td>
                                        <td>{{ $distribution->distributed_at->format('M j, Y') }}</td>
                                        <td><span class="status status-green">{{ str($distribution->transaction_status)->title() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                </section>
            @endif
        </div>

        <aside class="rsbsa-side-column">
            <section class="panel qr-panel">
                <span class="eyebrow">FARMER ID CARD</span>
                <h2>Scan to identify</h2>
                <p>This QR code contains the farmer's unique AgriQR ID.</p>
                <div class="qr-code-box"><canvas data-qr-value="{{ $farmer->farmer_id }}" aria-label="QR code for {{ $farmer->full_name }}"></canvas></div>
                <strong class="qr-farmer-code">{{ $farmer->farmer_id }}</strong>
                @if (auth()->user()->canUseButton('farmers.print'))<button class="button button-secondary button-wide no-print" type="button" onclick="window.print()">Print farmer card</button>@endif
            </section>

            <section class="panel rsbsa-status-card">
                <span class="detail-label">RSBSA VERIFICATION</span>
                @if ($rsbsa)
                    <span class="status {{ $rsbsa->verification_status === 'verified' ? 'status-green' : 'status-amber' }}">{{ str($rsbsa->verification_status)->title() }}</span>
                    <p>Registration: {{ str($rsbsa->registration_status)->title() }}</p>
                    @if ($rsbsa->registration_date)
                        <p>Registered {{ $rsbsa->registration_date->format('M j, Y') }}</p>
                    @endif
                @else
                    <span class="status status-neutral">Not registered</span>
                    <p>No RSBSA registration has been recorded.</p>
                @endif
            </section>
        </aside>
    </div>
@endsection

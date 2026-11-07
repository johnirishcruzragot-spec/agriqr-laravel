<div class="distribution-trend" role="img" aria-label="{{ $chartLabel }}">
    <div class="distribution-trend-grid" aria-hidden="true">
        @foreach ($chart as $point)
            <div class="distribution-trend-column" title="{{ $point['date'] }}: {{ $point['total'] }} completed">
                <strong class="distribution-trend-value">{{ $point['total'] ?: '' }}</strong>
                <div class="distribution-trend-track">
                    <span class="distribution-trend-bar" style="height: {{ $point['height'] }}%"></span>
                </div>
                <span class="distribution-trend-day">{{ $point['label'] }}</span>
                <small class="distribution-trend-date">{{ $point['date'] }}</small>
            </div>
        @endforeach
    </div>
</div>

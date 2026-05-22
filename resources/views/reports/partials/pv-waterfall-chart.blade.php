<section style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="font-size: 16px; margin: 0 0 12px;">PV waterfall</h2>

    <div style="display: grid; gap: 10px;">
        @foreach ($steps as $step)
            <div>
                <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 12px; margin-bottom: 4px;">
                    <span style="font-weight: 700;">{{ $step['label'] }}</span>
                    <span>{{ $step['value'] }}</span>
                </div>
                <div style="position: relative; height: 28px; background: #f3f4f6; border-radius: 6px; overflow: hidden;">
                    <div style="position: absolute; top: 5px; height: 18px; border-radius: 3px; left: {{ $step['left'] }}%; width: {{ $step['width'] }}%; background: {{ $step['color'] }};"></div>
                </div>
            </div>
        @endforeach
    </div>
</section>

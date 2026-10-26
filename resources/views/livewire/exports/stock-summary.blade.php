@php $th = 'font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;'; @endphp
<table>
    <tr>
        <td colspan="{{ 3 + count($outlets) }}" style="font-size: 14px;"><strong>Ringkasan Sisa Stok per Brand</strong></td>
    </tr>
    <tr>
        <td colspan="{{ 3 + count($outlets) }}">Per tanggal: {{ $dateLabel }} · Outlet: {{ implode(', ', $outlets) }}</td>
    </tr>
    <tr></tr>

    <tr>
        <th style="{{ $th }}">Brand</th>
        <th style="{{ $th }}">Jumlah Barang</th>
        @foreach ($outlets as $name)
            <th style="{{ $th }}">Stok {{ $name }}</th>
        @endforeach
        <th style="{{ $th }}">Total Stok</th>
        <th style="{{ $th }}">Nilai Stok (Harga Jual)</th>
    </tr>

    @foreach ($brands as $brand)
        <tr>
            <td style="border: 1px solid #ddd;">{{ $brand['brand_name'] }}</td>
            <td style="border: 1px solid #ddd;">{{ count($brand['items']) }}</td>
            @foreach ($outlets as $outletId => $name)
                <td style="border: 1px solid #ddd;">{{ collect($brand['items'])->sum(fn($i) => $i['outlets'][$outletId] ?? 0) }}</td>
            @endforeach
            <td style="border: 1px solid #ddd; font-weight: bold;">{{ $brand['qty'] }}</td>
            <td style="border: 1px solid #ddd;" data-format="#,##0">{{ $brand['sell_value'] }}</td>
        </tr>
    @endforeach

    <tr>
        <td style="font-weight: bold; border: 1px solid #000;">TOTAL</td>
        <td style="font-weight: bold; border: 1px solid #000;">{{ collect($brands)->sum(fn($b) => count($b['items'])) }}</td>
        @foreach ($outlets as $outletId => $name)
            <td style="font-weight: bold; border: 1px solid #000;">{{ collect($brands)->flatMap(fn($b) => $b['items'])->sum(fn($i) => $i['outlets'][$outletId] ?? 0) }}</td>
        @endforeach
        <td style="font-weight: bold; border: 1px solid #000;">{{ collect($brands)->sum('qty') }}</td>
        <td style="font-weight: bold; border: 1px solid #000;" data-format="#,##0">{{ collect($brands)->sum('sell_value') }}</td>
    </tr>
</table>

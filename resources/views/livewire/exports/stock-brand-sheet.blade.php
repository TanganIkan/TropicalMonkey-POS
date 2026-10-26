@php
    $th = 'font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;';
    $td = 'border: 1px solid #ddd;';
    $cols = 8 + count($outlets);
@endphp
<table>
    <tr>
        <td colspan="{{ $cols }}" style="font-size: 14px;"><strong>Sisa Stok Brand: {{ $data['brand_name'] }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $cols }}">Per tanggal: {{ $dateLabel }} · Outlet: {{ implode(', ', $outlets) }}</td>
    </tr>
    <tr></tr>

    <tr>
        <th style="{{ $th }}">Produk</th>
        <th style="{{ $th }}">Varian</th>
        <th style="{{ $th }}">Kategori</th>
        <th style="{{ $th }}">SKU</th>
        <th style="{{ $th }}">Barcode</th>
        @foreach ($outlets as $name)
            <th style="{{ $th }}">Stok {{ $name }}</th>
        @endforeach
        <th style="{{ $th }}">Total Stok</th>
        <th style="{{ $th }}">Harga Jual / Unit</th>
        <th style="{{ $th }}">Nilai Stok (Harga Jual)</th>
    </tr>

    @foreach ($data['items'] as $item)
        <tr>
            <td style="{{ $td }}">{{ $item['product_name'] }}</td>
            <td style="{{ $td }}">{{ $item['variant'] ?? '-' }}</td>
            <td style="{{ $td }}">{{ $item['category'] ?? '-' }}</td>
            {{-- data-type="s": SKU/barcode angka panjang tetap teks, tidak jadi notasi ilmiah --}}
            <td style="{{ $td }}" data-type="s">{{ $item['sku'] ?? '-' }}</td>
            <td style="{{ $td }}" data-type="s">{{ $item['barcode'] ?? '-' }}</td>
            @foreach ($outlets as $outletId => $name)
                <td style="{{ $td }}">{{ $item['outlets'][$outletId] ?? 0 }}</td>
            @endforeach
            <td style="{{ $td }} font-weight: bold;">{{ $item['qty'] }}</td>
            <td style="{{ $td }}" data-format="#,##0">{{ $item['sell_price'] }}</td>
            <td style="{{ $td }}" data-format="#,##0">{{ $item['sell_value'] }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="5" style="text-align: right; font-weight: bold; border: 1px solid #000;">TOTAL</td>
        @foreach ($outlets as $outletId => $name)
            <td style="font-weight: bold; border: 1px solid #000;">{{ collect($data['items'])->sum(fn($i) => $i['outlets'][$outletId] ?? 0) }}</td>
        @endforeach
        <td style="font-weight: bold; border: 1px solid #000;">{{ $data['qty'] }}</td>
        <td style="border: 1px solid #000;"></td>
        <td style="font-weight: bold; border: 1px solid #000;" data-format="#,##0">{{ $data['sell_value'] }}</td>
    </tr>
</table>

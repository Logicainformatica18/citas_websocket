@component('mail::message')
# 📦 Nueva Transferencia Registrada

**Descripción:** {{ $transfer->description }}

**Detalles:** {{ $transfer->details }}

---

**Emisor:** {{ $transfer->sender_firstname }} {{ $transfer->sender_lastname }}
📧 {{ $transfer->sender_email }}

**Receptor:** {{ $transfer->receiver_firstname }} {{ $transfer->receiver_lastname }}
📧 {{ $transfer->receiver_email }}

@if($transfer->file_1)
---
📎 Se adjuntó un archivo: `{{ $transfer->file_1 }}`
@endif

@if($transfer->articles->count())
---

**🧾 Lista de artículos asociados:**

<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 13px;">
    <thead style="background-color: #f3f4f6;">
        <tr>
            <th align="left">#</th>
            <th align="left">Código</th>
            <th align="left">Título</th>
            <th align="left">Descripción</th>
            <th align="left">Condición</th>
            <th align="left">Estado</th>
            <th align="right">Cantidad</th>
            <th align="right">Precio</th>
        </tr>
    </thead>
    <tbody>
        @php $total = 0; $totalQty = 0; @endphp
        @foreach($transfer->articles as $index => $article)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $article->code }}</td>
            <td>{{ $article->title }}</td>
            <td>{{ $article->description }}</td>
            <td>{{ ucfirst($article->condition) }}</td>
            <td>{{ ucfirst($article->state) }}</td>
            <td align="right">{{ $article->quanty }}</td>
            <td align="right">S/ {{ number_format($article->price, 2) }}</td>
        </tr>
        @php
            $total += $article->price;
            $totalQty += $article->quanty;
        @endphp
        @endforeach
        <tr style="background-color: #f9fafb;">
            <td colspan="6" align="right"><strong>Total</strong></td>
            <td align="right"><strong>{{ $totalQty }}</strong></td>
            <td align="right"><strong>S/ {{ number_format($total, 2) }}</strong></td>
        </tr>
    </tbody>
</table>
@endif

@component('mail::button', ['url' => url('/articles/' . $transfer->id . '/export-excel')])
Exportar artículos en Excel
@endcomponent
@component('mail::button', ['url' => url('/transfer-confirmation/' . $transfer->confirmation_token)])
✅ Confirmar recepción
@endcomponent


@endcomponent

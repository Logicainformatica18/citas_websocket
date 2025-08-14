<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Pago</title>
</head>
<body>
    <h1>Hola {{ $payment->full_name }}</h1>

    <p>Hemos recibido tu pago.</p>

    <ul>
        <li><strong>Correo:</strong> {{ $payment->email }}</li>
        <li><strong>DNI:</strong> {{ $payment->dni }}</li>
        <li><strong>Monto:</strong> S/ {{ number_format($payment->amount, 2) }}</li>
        @if($payment->receipt_number)
            <li><strong>Comprobante:</strong> {{ $payment->receipt_number }}</li>
        @endif
        @if($payment->project)
            <li><strong>Proyecto:</strong> {{ $payment->project->descripcion }}</li>
        @endif
        @if($payment->mz_lote)
            <li><strong>MZ - Lote:</strong> {{ $payment->mz_lote }}</li>
        @endif
    </ul>

    <p>Gracias por confiar en nosotros.</p>
</body>
</html>

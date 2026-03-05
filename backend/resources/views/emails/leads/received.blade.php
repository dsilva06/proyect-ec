<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo lead</title>
</head>
<body>
    <h2>Nuevo contacto recibido</h2>
    <p><strong>Tipo:</strong> {{ $lead->type }}</p>
    <p><strong>Nombre:</strong> {{ $lead->full_name }}</p>
    <p><strong>Email:</strong> {{ $lead->email }}</p>
    <p><strong>Telefono:</strong> {{ $lead->phone ?: '-' }}</p>
    <p><strong>Empresa:</strong> {{ $lead->company ?: '-' }}</p>
    <p><strong>Mensaje:</strong></p>
    <p>{{ $lead->message }}</p>
    <hr>
    <p><strong>Fuente:</strong> {{ $lead->source ?: '-' }}</p>
    <p><strong>Fecha:</strong> {{ optional($lead->created_at)->toDateTimeString() }}</p>
</body>
</html>

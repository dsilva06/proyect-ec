<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación de equipo</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
    <h2>¡Te invitaron a un equipo!</h2>
    <p>
        Has recibido una invitación para unirte al equipo
        <strong>{{ $invite->team?->display_name ?? 'Equipo' }}</strong>.
    </p>
    <p>
        Para continuar, crea tu cuenta o inicia sesión con este correo y acepta la invitación desde tu perfil.
    </p>
    <p>
        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 10px 16px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Ir al registro
        </a>
    </p>
    <p style="font-size: 12px; color: #6b7280;">
        Este enlace expirará el {{ optional($invite->expires_at)->format('d/m/Y') ?? 'pronto' }}.
    </p>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu correo - ESTARS PADEL TOUR</title>
</head>
<body style="margin:0; padding:0; background:#f5f6fb;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f5f6fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; background:#1a1a35; border-radius:18px; overflow:hidden; border:1px solid rgba(240,232,212,0.16);">
                <tr>
                    <td style="padding:24px 28px; background:linear-gradient(135deg,#2b2e72 0%,#1a1a35 60%,#16152a 100%);">
                        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; color:#b7d051; font-size:12px; letter-spacing:1.4px; text-transform:uppercase;">
                            Estars Padel Tour
                        </p>
                        <h1 style="margin:12px 0 0; font-family:Arial,Helvetica,sans-serif; color:#f0e8d4; font-size:28px; line-height:1.2;">
                            Verifica tu correo
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px; font-family:Arial,Helvetica,sans-serif; color:#f0e8d4;">
                        <p style="margin:0 0 14px; font-size:16px; line-height:1.6;">
                            Hola {{ $name }},
                        </p>
                        <p style="margin:0 0 14px; font-size:15px; line-height:1.7; color:rgba(240,232,212,0.92);">
                            Tu cuenta fue creada correctamente. Para activarla y poder iniciar sesión, confirma que este correo te pertenece.
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 20px;">
                            <tr>
                                <td align="center" style="border-radius:999px; background:#eb5f3c;">
                                    <a href="{{ $verificationUrl }}" style="display:inline-block; padding:13px 28px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; color:#f0e8d4; text-decoration:none; border-radius:999px;">
                                        Verificar mi correo
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 10px; font-size:13px; line-height:1.6; color:rgba(240,232,212,0.72);">
                            Si el botón no funciona, copia y pega este enlace en tu navegador:
                        </p>
                        <p style="margin:0 0 16px; word-break:break-all;">
                            <a href="{{ $verificationUrl }}" style="color:#b7d051; font-size:13px; text-decoration:underline;">
                                {{ $verificationUrl }}
                            </a>
                        </p>

                        <p style="margin:0; font-size:13px; line-height:1.6; color:rgba(240,232,212,0.72);">
                            Si no creaste esta cuenta, puedes ignorar este mensaje.
                            Luego de verificar, podrás iniciar sesión en
                            <a href="{{ $loginUrl }}" style="color:#b7d051;">ESTARS PADEL TOUR</a>.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

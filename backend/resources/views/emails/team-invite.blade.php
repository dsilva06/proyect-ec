<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitacion de equipo - ESTARS PADEL TOUR</title>
    <style>
        @media screen and (max-width: 600px) {
            .email-shell { padding: 14px 8px !important; }
            .email-card { border-radius: 16px !important; }
            .email-header, .email-body { padding: 20px 16px !important; }
            .email-logo-wrap { padding: 8px 10px !important; border-radius: 12px !important; }
            .email-logo { width: 170px !important; }
            .email-title { font-size: 27px !important; line-height: 1.12 !important; }
            .email-button { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#edf0f8;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-shell" style="background:#edf0f8; padding:22px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-card" style="max-width:640px; background:#12163a; border-radius:22px; overflow:hidden; border:1px solid #273162;">
                <tr>
                    <td class="email-header" style="padding:26px 28px; background:linear-gradient(135deg,#1a2054 0%,#111539 58%,#2b1135 100%); border-bottom:1px solid #2f3a70;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="email-logo-wrap" style="padding:10px 14px; border-radius:14px; background:#f3ecd8; border:1px solid rgba(18,22,58,0.2);">
                                    <img src="{{ $logoUrl }}" alt="ESTARS PADEL TOUR" width="235" class="email-logo" style="display:block; width:235px; max-width:100%; height:auto; border:0;">
                                </td>
                            </tr>
                        </table>

                        <span style="display:inline-block; margin-top:16px; padding:6px 12px; border-radius:999px; background:rgba(183,208,81,0.16); border:1px solid rgba(183,208,81,0.45); font-family:Arial,Helvetica,sans-serif; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#dceda0;">
                            Invitacion de pareja
                        </span>
                        <h1 class="email-title" style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:33px; line-height:1.08; color:#f3ecd8;">
                            {{ $hasExistingAccount ? 'Tu pareja ya dejo el equipo listo' : 'Te estan esperando para completar el equipo' }}
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td class="email-body" style="padding:28px;">
                        <p style="margin:0 0 14px; font-family:Arial,Helvetica,sans-serif; font-size:17px; line-height:1.6; color:#f3ecd8;">
                            <strong style="color:#ffffff;">{{ $captainName }}</strong> te invito a jugar el torneo
                            <strong style="color:#ffffff;">{{ $tournamentName }}</strong>.
                        </p>
                        <p style="margin:0 0 18px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.7; color:#e6e9f7;">
                            Categoria: <strong style="color:#ffffff;">{{ $categoryName }}</strong><br>
                            El pago del equipo ya fue cubierto. Falta tu aceptacion para completar la inscripcion.
                        </p>

                        @if($hasExistingAccount)
                            <p style="margin:0 0 20px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.7; color:#e6e9f7;">
                                Inicia sesion con este mismo correo y revisa tu perfil en la pestana de invitaciones para aceptar tu lugar en el torneo.
                            </p>
                        @else
                            <p style="margin:0 0 20px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.7; color:#e6e9f7;">
                                Registra tu cuenta con este correo, verifica tu email y la invitacion aparecera automaticamente en tu perfil para que puedas aceptarla.
                            </p>
                        @endif

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                            <tr>
                                <td align="center" style="border-radius:999px; background:linear-gradient(120deg,#ff653f,#ff9a4f);">
                                    <a href="{{ $actionUrl }}" class="email-button" style="display:inline-block; padding:14px 30px; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#1a1021; text-decoration:none; border-radius:999px;">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 20px;">
                            <tr>
                                <td style="padding:14px 15px; border-radius:14px; border:1px solid rgba(240,236,216,0.2); background:rgba(240,236,216,0.06);">
                                    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#d9ddef;">
                                        Esta invitacion estara disponible hasta el
                                        <strong style="color:#ffffff;">{{ optional($invite->expires_at)->format('d/m/Y H:i') ?? 'cierre de inscripciones' }}</strong>.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#aeb7dc;">
                            Si este correo no te corresponde, puedes ignorarlo.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

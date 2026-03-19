<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificacion de correo - ESTARS PADEL TOUR</title>
    <style>
        @media screen and (max-width: 600px) {
            .email-shell {
                padding: 14px 8px !important;
            }

            .email-card {
                border-radius: 16px !important;
            }

            .email-header {
                padding: 20px 16px !important;
            }

            .email-logo-wrap {
                padding: 8px 10px !important;
                border-radius: 12px !important;
            }

            .email-logo {
                width: 170px !important;
            }

            .email-kicker {
                font-size: 10px !important;
                padding: 5px 10px !important;
            }

            .email-title {
                margin-top: 12px !important;
                font-size: 27px !important;
                line-height: 1.12 !important;
            }

            .email-body {
                padding: 20px 16px !important;
            }

            .email-greeting {
                font-size: 17px !important;
            }

            .email-text {
                font-size: 15px !important;
                line-height: 1.65 !important;
            }

            .email-button-block {
                width: 100% !important;
            }

            .email-button-block td {
                display: block !important;
                width: 100% !important;
            }

            .email-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
                text-align: center !important;
                padding: 14px 18px !important;
            }

            .email-alt-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
                text-align: center !important;
            }

            .email-note-box {
                padding: 12px 12px !important;
            }

            .email-footer {
                font-size: 12px !important;
            }
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
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 14px;">
                            <tr>
                                <td align="left">
                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td class="email-logo-wrap" style="padding:10px 14px; border-radius:14px; background:#f3ecd8; border:1px solid rgba(18,22,58,0.2);">
                                                <img
                                                    src="{{ $logoUrl }}"
                                                    alt="ESTARS PADEL TOUR"
                                                    width="235"
                                                    class="email-logo"
                                                    style="display:block; width:235px; max-width:100%; height:auto; border:0;"
                                                >
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <span class="email-kicker" style="display:inline-block; padding:6px 12px; border-radius:999px; background:rgba(183,208,81,0.16); border:1px solid rgba(183,208,81,0.45); font-family:Arial,Helvetica,sans-serif; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#dceda0;">
                            Activacion de cuenta
                        </span>
                        <h1 class="email-title" style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:33px; line-height:1.08; color:#f3ecd8;">
                            Verificacion de correo
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td class="email-body" style="padding:28px;">
                        <p class="email-greeting" style="margin:0 0 12px; font-family:Arial,Helvetica,sans-serif; font-size:18px; line-height:1.5; color:#f3ecd8;">
                            Hola {{ $name }},
                        </p>
                        <p class="email-text" style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.7; color:#e6e9f7;">
                            Bienvenido a <strong style="color:#ffffff;">ESTARS PADEL TOUR</strong>.
                            Tu cuenta fue creada correctamente y solo falta un paso para activarla.
                        </p>
                        <p class="email-text" style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.7; color:#e6e9f7;">
                            Confirma que este correo te pertenece y en segundos podras iniciar sesion para gestionar torneos, invitaciones y pagos.
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" class="email-button-block" style="margin:0 0 12px;">
                            <tr>
                                <td align="center" style="border-radius:999px; background:linear-gradient(120deg,#ff653f,#ff9a4f);">
                                    <a href="{{ $verificationEntryUrl }}" class="email-button" style="display:inline-block; padding:14px 30px; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#1a1021; text-decoration:none; border-radius:999px;">
                                        Verificar mi correo
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" class="email-button-block" style="margin:0 0 22px;">
                            <tr>
                                <td align="center" style="border-radius:999px; border:1px solid rgba(183,208,81,0.55); background:rgba(183,208,81,0.08);">
                                    <a href="{{ $verificationEntryUrl }}" class="email-alt-button" style="display:inline-block; padding:10px 20px; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:700; color:#dceda0; text-decoration:none; border-radius:999px;">
                                        Abrir enlace alterno
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 20px; border-collapse:separate;">
                            <tr>
                                <td class="email-note-box" style="padding:14px 15px; border-radius:14px; border:1px solid rgba(240,236,216,0.2); background:rgba(240,236,216,0.06);">
                                    <p class="email-footer" style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#d9ddef;">
                                        Si no solicitaste esta cuenta, puedes ignorar este correo.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="email-footer" style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#aeb7dc;">
                            Despues de verificar, podras iniciar sesion desde
                            <a href="{{ $loginUrl }}" style="color:#b7d051; text-decoration:none; font-weight:700;">estarspadeltour.com/login</a>.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

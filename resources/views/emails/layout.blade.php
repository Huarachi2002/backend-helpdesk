<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notificación Mesa de Ayuda' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 28px 32px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #bfdbfe;
        }
        .body-content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .message {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }
        .ticket-card {
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin: 20px 0;
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #cbd5e1;
            font-size: 13px;
        }
        .ticket-row:last-child {
            border-bottom: none;
        }
        .ticket-label {
            font-weight: 600;
            color: #64748b;
        }
        .ticket-val {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-open { background-color: #e0f2fe; color: #0369a1; }
        .badge-in_progress { background-color: #fef3c7; color: #b45309; }
        .badge-waiting { background-color: #f3e8ff; color: #7e22ce; }
        .badge-resolved { background-color: #dcfce7; color: #15803d; }
        .badge-closed { background-color: #f1f5f9; color: #475569; }

        .badge-urgent { background-color: #fee2e2; color: #b91c1c; }
        .badge-high { background-color: #ffedd5; color: #c2410c; }
        .badge-medium { background-color: #fef9c3; color: #a16207; }
        .badge-low { background-color: #ecfdf5; color: #047857; }

        .btn-wrapper {
            text-align: center;
            margin: 28px 0 16px 0;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
        .footer a {
            color: #64748b;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mesa de Ayuda</h1>
            <p>Sistema Integral de Soporte Técnico Universitario</p>
        </div>

        <div class="body-content">
            @yield('content')
        </div>

        <div class="footer">
            <p>@yield('footer_note', 'Este es un correo automático generado por el Sistema de Mesa de Ayuda. Por favor, no responda directamente a este correo.')</p>
            <p>&copy; {{ date('Y') }} Mesa de Ayuda UAGRM. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

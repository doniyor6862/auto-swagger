<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.1.0/swagger-ui.css">
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        
        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }
        
        body {
            margin: 0;
            background: #fafafa;
        }
        
        .swagger-ui .topbar {
            background-color: #222;
        }

        .swagger-ui .info .title small.version-stamp {
            background-color: #1f8feb;
        }

        .swagger-ui .opblock.opblock-get .opblock-summary-method {
            background: #1f8feb;
        }

        .swagger-ui .btn.execute {
            background-color: #0f6ab4;
        }

        /* Dark theme overrides */
        body.swagger-ui-dark {
            background: #222;
            color: #fff;
        }

        body.swagger-ui-dark .swagger-ui,
        body.swagger-ui-dark .swagger-ui .info .title,
        body.swagger-ui-dark .swagger-ui .scheme-container,
        body.swagger-ui-dark .swagger-ui select,
        body.swagger-ui-dark .swagger-ui label,
        body.swagger-ui-dark .swagger-ui .response-col_status,
        body.swagger-ui-dark .swagger-ui .renderedMarkdown p,
        body.swagger-ui-dark .swagger-ui .opblock .opblock-section-header h4,
        body.swagger-ui-dark .swagger-ui .opblock .opblock-summary-description,
        body.swagger-ui-dark .swagger-ui .tab li,
        body.swagger-ui-dark .swagger-ui .model-title,
        body.swagger-ui-dark .swagger-ui .model {
            color: #fff;
        }

        body.swagger-ui-dark .swagger-ui .opblock-description-wrapper p, 
        body.swagger-ui-dark .swagger-ui .opblock-external-docs-wrapper p, 
        body.swagger-ui-dark .swagger-ui .opblock-title_normal p {
            color: #ddd;
        }

        body.swagger-ui-dark .swagger-ui .opblock .opblock-section-header {
            background: #333;
        }

        body.swagger-ui-dark .swagger-ui section.models {
            background: #1a1a1a;
            border-color: #333;
        }

        body.swagger-ui-dark .swagger-ui .scheme-container {
            background: #1a1a1a;
        }

        body.swagger-ui-dark .swagger-ui .model-container,
        body.swagger-ui-dark .swagger-ui .model-container:hover {
            background: #1a1a1a;
            border-color: #333;
        }

        body.swagger-ui-dark .swagger-ui .opblock {
            background: #1a1a1a;
            border-color: #333;
        }

        body.swagger-ui-dark .swagger-ui table.model tr.property-row td {
            color: #f0f0f0;
            border-color: #333;
        }
    </style>
</head>
<body @if(isset($uiSettings['theme']) && $uiSettings['theme'] == 'dark') class="swagger-ui-dark" @endif>
    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.1.0/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.1.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{{ $swaggerJsonUrl }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                docExpansion: "{{ $uiSettings['doc_expansion'] ?? 'list' }}",
                displayRequestDuration: {{ $uiSettings['display_request_duration'] ? 'true' : 'false' }},
                persistAuthorization: {{ $uiSettings['persist_authorization'] ? 'true' : 'false' }},
            });

            window.ui = ui;
        };
    </script>
</body>
</html>

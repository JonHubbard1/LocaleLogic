<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>API Documentation - LocaleLogic</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">LocaleLogic API Documentation</h1>
                <p class="text-lg text-gray-600 dark:text-gray-400">UK Geography Microservice REST API</p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-500">Version 1.0</p>
            </div>

            {{-- Introduction --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Introduction</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    The LocaleLogic API provides access to comprehensive UK geography data including postcode lookups,
                    property coordinates, electoral boundaries, and administrative geography information.
                </p>
                <p class="text-gray-700 dark:text-gray-300">
                    All API requests require authentication using Laravel Sanctum tokens. Contact your administrator
                    to obtain an API token for your application.
                </p>
            </div>

            {{-- Base URL --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">Base URL</h3>
                <code class="block bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-100 px-4 py-3 rounded font-mono text-sm">
                    {{ url('/api/v1') }}
                </code>
            </div>

            {{-- Authentication --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Authentication</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    All requests must include your API token in the Authorization header:
                </p>
                <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>
            </div>

            {{-- Endpoints --}}
            <div class="space-y-8">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">API Endpoints</h2>

                {{-- Postcode Lookup --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Postcode Lookup</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Get geography data for a UK postcode</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /postcodes/{postcode}
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Query Parameters:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start">
                                    <code class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-2 py-1 rounded text-sm font-mono">include</code>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">(optional) - Set to "uprns" to include property coordinates</span>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Request:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>curl -X GET "{{ url('/api/v1/postcodes/SN12 6AE') }}?include=uprns" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Response:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>{
  "data": {
    "postcode": "SN12 6AE",
    "latitude": 51.234567,
    "longitude": -2.123456,
    "property_count": 25,
    "geography": {
      "ward": { "code": "E05...", "name": "..." },
      "parish": { "code": "E04...", "name": "..." },
      "local_authority": { "code": "E06...", "name": "..." },
      "constituency": { "code": "E14...", "name": "..." }
    },
    "uprns": [...],
    "coordinate_offset": { "latitude": 0, "longitude": 0 }
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- List Councils --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">List All Councils</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Get all county, unitary, or district councils</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /councils
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Query Parameters:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start">
                                    <code class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-2 py-1 rounded text-sm font-mono">type</code>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">(optional) - Filter by "county", "unitary", or "district"</span>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Request:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>curl -X GET "{{ url('/api/v1/councils') }}?type=county" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Get Districts --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Get District Councils</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">List all district councils in a county area</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /councils/{countyCode}/districts
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Request:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>curl -X GET "{{ url('/api/v1/councils/E10000036/districts') }}" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Get Electoral Divisions --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Get Electoral Divisions</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Get all electoral divisions in a county with postcodes</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /councils/{councilCode}/divisions
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Response:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>{
  "data": [
    {
      "gss_code": "E05...",
      "name": "Division Name",
      "postcode_count": 450,
      "postcodes": ["SN1 1AA", "SN1 1AB", ...]
    }
  ],
  "meta": {
    "council_code": "E10...",
    "council_name": "...",
    "division_count": 12
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Get Electoral Wards --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Get Electoral Wards</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Get all electoral wards in a unitary/district council with postcodes</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /councils/{councilCode}/wards
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Request:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>curl -X GET "{{ url('/api/v1/councils/E06000054/wards') }}" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Get Parishes --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Get Parishes</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Get all parishes in a council area with postcodes</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">GET</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Endpoint:</p>
                            <code class="block bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2 rounded font-mono text-sm">
                                GET /councils/{councilCode}/parishes
                            </code>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Request:</p>
                            <pre class="bg-gray-900 text-gray-100 px-4 py-3 rounded-lg overflow-x-auto text-sm font-mono"><code>curl -X GET "{{ url('/api/v1/councils/E06000054/parishes') }}" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Error Responses --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 mt-8">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Error Responses</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    The API uses standard HTTP status codes to indicate success or failure:
                </p>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/20 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20 mr-3">200</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Success - Request completed successfully</span>
                    </div>
                    <div class="flex items-start">
                        <span class="inline-flex items-center rounded-md bg-yellow-50 dark:bg-yellow-900/20 px-2 py-1 text-xs font-medium text-yellow-700 dark:text-yellow-400 ring-1 ring-inset ring-yellow-600/20 dark:ring-yellow-500/20 mr-3">401</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Unauthorized - Invalid or missing API token</span>
                    </div>
                    <div class="flex items-start">
                        <span class="inline-flex items-center rounded-md bg-red-50 dark:bg-red-900/20 px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 ring-1 ring-inset ring-red-600/20 dark:ring-red-500/20 mr-3">404</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Not Found - Resource does not exist</span>
                    </div>
                    <div class="flex items-start">
                        <span class="inline-flex items-center rounded-md bg-red-50 dark:bg-red-900/20 px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 ring-1 ring-inset ring-red-600/20 dark:ring-red-500/20 mr-3">422</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Unprocessable Entity - Invalid input data</span>
                    </div>
                </div>
            </div>

            {{-- Rate Limiting --}}
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-6 mt-8">
                <h3 class="text-lg font-semibold text-yellow-900 dark:text-yellow-100 mb-2">Rate Limiting</h3>
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    API requests are subject to rate limiting. Contact your administrator for specific limits for your API token.
                </p>
            </div>

            {{-- Footer --}}
            <div class="mt-12 text-center text-sm text-gray-500 dark:text-gray-500">
                <p>For API access, contact your administrator to obtain authentication credentials.</p>
                <p class="mt-2">LocaleLogic API v1.0 - UK Geography Microservice</p>
            </div>
        </div>
    </div>
    @fluxScripts
</body>
</html>

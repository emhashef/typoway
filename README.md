# TypoWay

**TypoWay** is a Laravel package that generates TypeScript route definitions, including API endpoints, URLs, and Inertia.js form helpers. It provides a strongly typed way to work with routes in TypeScript-based front-end applications.

> **⚠️Warning**
> This package is currently in beta and may have breaking changes. Use with caution in production environments.

## Features

- 🚀 **Generate API route definitions** with TypeScript types
- 🔗 **Generate URL helpers** for clean and structured navigation
- ⚡ **Inertia.js form helpers** for better integration with Laravel-Inertia apps
- 🔥 **Automatic TypeScript typing** for request parameters


## Note
TypoWay utilizes the `dedoc/scramble` package to analyze Laravel routes and extract type definitions.

## Installation

Install the package via Composer:

```sh
composer require emhashef/typoway --dev
```

## Usage

Run the command to generate TypeScript route files:

```sh
php artisan typoway:generate
```

### Options

- `--apis` → Generate API routes only
- `--inertia` → Generate Inertia.js routes only

Example:

```sh
php artisan typoway:generate --apis
```

## Output

Running the command generates the following TypeScript files:

- `routes.ts` – Contains all routes, APIs, and Inertia form helpers.
- `routes.urls.setup.ts` – Handles URL generation.
- `routes.apis.setup.ts` – Handles API request methods.
- `routes.inertia.setup.ts` – Handles Inertia form handling.

Example Output:

```ts
export const urls = {
  test: {
    index: () => `/api/test`,
    storeArray: () => `/api/store-array`,
  },
};

export const apis = {
  test: {
    index: (data?: { name?: string }): ApiResponse<{ name?: string }> =>
      request("get", urls.test.index(), data),
    storeArray: (data?: { bob?: number[] }): ApiResponse<any> =>
      request("post", urls.test.storeArray(), data),
  },
};
```

## Configuration

To publish the configuration file, run the following command:
```sh
php artisan vendor:publish --tag=typoway
```

You can configure excluded route names in the config/typoway.php file. These routes will not be included in the generated TypeScript definitions.

```php
return [
    'except-routes' => [
        "filament*",
        "scramble*",
        "debugbar*",
        "dusk*",
        "ignition*",
        "livewire*",
    ]
];
```

Modify this array to exclude additional route names as needed. Wildcards (\*) can be used to match multiple routes.

## License

This package is open-source and released under the MIT License.

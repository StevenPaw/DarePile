# Standard Silverstripe Template
with Vite, SCSS and more

## Using as a Template
1. Click **"Use this template"** on GitHub to create a new repository
2. Clone your new repository
3. Update the project name in `.ddev/config.yaml` (`name:` field)
4. Update `VITE_DEV_SERVER_URL` in `.env` to match the new project name (e.g. `https://<projectname>.ddev.site:5173`)
5. Run `ddev start`

## Installation
1. Clone this repo
2. Run `ddev start` — DDEV automatically configures `.env` for the database and environment; the only value to set manually is `VITE_DEV_SERVER_URL` in `.env` (e.g. `https://standard-silverstripe-template.ddev.site:5173`)

## Development
Run `ddev exec yarn dev` to start the Vite dev server on port 5173.

## Build
Run `ddev exec yarn build` to build the project. The build artifacts will be stored in `app/client/dist/`.

## Credits
- [Silverstripe](https://silverstripe.org/)
- [Vite](https://vitejs.dev/)

## Installed Modules
- [silverstripe/recipe-cms](https://github.com/silverstripe/recipe-cms)
- [silverstripe/silverstripe-elemental](https://github.com/silverstripe/silverstripe-elemental)
- [silverstripe/recipe-plugin](https://github.com/silverstripe/recipe-plugin)
- [atwx/silverstripe-projectinfo](https://github.com/atwx/silverstripe-projectinfo)
- [atwx/silverstripe-vitehelper](https://github.com/atwx/silverstripe-vitehelper)
- [atwx/silverstripe-gate-client](https://github.com/atwx/silverstripe-gate-client)

## Recommended Modules
| Module                                              | Description                                                     |
|--|--|
| colymba/gridfield-bulk-editing-tools                | A Gridfield extension to allow bulk editing tools               |
| jonom/focuspoint                                    | A Image Extension to scale images into focus point based crops  |
| purplespider/silverstripe-basic-gallery-extension   | A simple gallery module for uploading many images to a page     |

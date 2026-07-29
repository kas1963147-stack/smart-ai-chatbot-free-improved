# Building Assets for Smart AI Chatbot

The frontend and admin UI assets in the `assets/admin-build` and `assets/frontend-build` directories are compiled using standard WordPress development tools.

To rebuild the assets from source:

1. Ensure you have Node.js and npm installed.
2. Open a terminal in the root directory of the plugin.
3. Run `npm install` to install all dependencies from `package.json`.
4. Run `npm run build` to compile the React code located in `assets/admin-react` and `assets/frontend-react` into the production builds.

The source code for all minified scripts is included in the plugin package under `assets/admin-react` and `assets/frontend-react` and is completely un-minified and human readable.

We use `@wordpress/scripts` to compile the React code, which is the official WordPress standard.

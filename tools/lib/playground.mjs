/*
 *  The one place the Playground CLI's version is named.
 *
 *  `npx @wp-playground/cli` resolves to the newest release, and on
 *  2026-09-21 that was 3.1.55, which cannot be installed: its dependency
 *  `@php-wasm/node-8-1@3.1.55` was never published, so every runner that
 *  spawned the bare name failed before booting. 3.1.54 is the version the
 *  4.0.2 gates ran on. Move the pin here when a newer release installs.
 */
export const PLAYGROUND_CLI = process.env.VGML_PLAYGROUND_CLI || '@wp-playground/cli@3.1.54';

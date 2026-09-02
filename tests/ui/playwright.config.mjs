import { defineConfig } from '@playwright/test';

/*
 *  The screens, driven.
 *
 *  Everything else in this repo checks that the code is well formed: PHP lints
 *  on 7.4 and 8.3, the query budgets hold, Plugin Check passes. None of that
 *  can see a button that runs, redirects, and says nothing -- or a score that
 *  reads 25 out of 25 above a card saying one file is unfiled. Both of those
 *  shipped. Both were found by a person clicking.
 *
 *  So this drives the admin the way a person does, against a real WordPress
 *  with a real library rather than a fixture. Every spec here is a bug that
 *  actually happened.
 *
 *      pnpm test:ui                               against the box
 *      UI_BASE=http://127.0.0.1:8899 pnpm test:ui  against Playground
 *
 *  Credentials come from UI_USER and UI_PASS. In CI a throwaway administrator
 *  is made for the run and deleted after it, so nothing long-lived is stored.
 */

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';

export default defineConfig( {
	testDir: '.',
	testMatch: '**/*.spec.mjs',
	// A describe run and a folder proposal both wait on a model.
	timeout: 120_000,
	expect: { timeout: 20_000 },
	// One at a time: these share one WordPress, and two specs changing settings
	// at once would be testing each other.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],
	use: {
		baseURL: BASE,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		ignoreHTTPSErrors: true,
	},
} );

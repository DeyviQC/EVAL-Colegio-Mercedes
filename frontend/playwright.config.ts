import { defineConfig } from '@playwright/test';
const pin=process.env.EVAL_TLS_SPKI;
if(pin!==undefined&&!/^[A-Za-z0-9+/]{43}=$/.test(pin))throw new Error('Invalid disposable SPKI pin');
// The owned harness supplies a per-run key exception, never global CA trust.
export default defineConfig({testDir:'./tests/e2e',fullyParallel:false,workers:1,retries:0,
  use:{channel:'chromium',ignoreHTTPSErrors:false,launchOptions:{args:pin?[`--ignore-certificate-errors-spki-list=${pin}`]:[]}},
  outputDir:'test-results',reporter:'list'});

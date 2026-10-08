import { defineConfig } from '@playwright/test';
// Tooling/discovery only: no browser download, certificate bypass or server startup.
export default defineConfig({testDir:'./tests/e2e',fullyParallel:false,retries:0,use:{ignoreHTTPSErrors:false},outputDir:'test-results',reporter:'list'});

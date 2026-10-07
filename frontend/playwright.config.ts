import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  workers: 1,
  expect: { timeout: 15000 },
  use: {
    baseURL: process.env.EVAL_URL ?? "http://127.0.0.1:8081",
    channel: process.env.EVAL_BROWSER ?? "chrome",
    headless: true,
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
});

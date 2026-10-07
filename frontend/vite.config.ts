import { defineConfig, type Plugin } from "vite";
import { gzipSync } from "node:zlib";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
const compressedAssets: Plugin = {
  name: "eval-local-gzip-assets",
  generateBundle(_options, bundle) {
    for (const output of Object.values(bundle)) {
      if (/\.(js|css)$/.test(output.fileName)) {
        this.emitFile({
          type: "asset",
          fileName: `${output.fileName}.gz`,
          source: gzipSync(
            output.type === "chunk" ? output.code : output.source,
          ),
        });
      }
    }
  },
};
export default defineConfig({
  base: "/client/",
  plugins: [react(), tailwindcss(), compressedAssets],
  server: { proxy: { "/api": "http://127.0.0.1:8081" } },
  build: { outDir: "../backend/public/client", emptyOutDir: true },
});

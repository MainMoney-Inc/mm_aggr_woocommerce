import * as esbuild from "esbuild";
import { cpSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const pluginRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const sdkRoot = path.resolve(pluginRoot, "../../sdks/javascript");

await esbuild.build({
  absWorkingDir: pluginRoot,
  entryPoints: [path.join(pluginRoot, "assets/js/bootstrap.ts")],
  bundle: true,
  format: "iife",
  outfile: path.join(pluginRoot, "assets/js/checkout.js"),
  platform: "browser",
  target: ["es2022"],
  sourcemap: true,
  loader: { ".png": "file" },
  alias: {
    "@mainmoney/js-core": path.join(sdkRoot, "packages/core/src/index.ts"),
    "@mainmoney/js-http": path.join(sdkRoot, "packages/http/src/index.ts"),
    "@mainmoney/js-checkout": path.join(sdkRoot, "packages/checkout/src/index.ts"),
  },
});

cpSync(
  path.join(sdkRoot, "packages/checkout/src/styles.css"),
  path.join(pluginRoot, "assets/js/checkout.css"),
);
cpSync(
  path.join(sdkRoot, "packages/checkout/src/assets/main_money_square.png"),
  path.join(pluginRoot, "assets/js/main_money_square.png"),
);

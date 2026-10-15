import { createSession } from "@mainmoneyapp/js-core";
import { createCheckout, mountCheckout } from "@mainmoneyapp/js-checkout";

type PluginConfig = {
  sessionUrl: string;
  logoUrl?: string;
};

type CheckoutBootConfig = {
  merchantBackendUrl: string;
  clientToken: string;
  pollUrl: string;
  pollHeaders: Record<string, string>;
  locale?: "en" | "fr";
  amount?: string | null;
  lockAmount?: boolean;
  reference?: string;
};

declare global {
  interface Window {
    mmAggrConfig?: PluginConfig;
  }
}

const SELECTOR = ".mm-aggr-checkout";
const MOUNTED = "data-mm-mounted";

/** Place the order once the widget reports a terminal SUCCESS; the server re-verifies the payment. */
function placeOrder(): void {
  const classic = document.querySelector<HTMLElement>("form.checkout #place_order");
  if (classic !== null) {
    classic.click();
    return;
  }
  document.querySelector<HTMLElement>(".wc-block-components-checkout-place-order-button")?.click();
}

async function mountOne(root: HTMLElement, plugin: PluginConfig): Promise<void> {
  const response = await fetch(plugin.sessionUrl, {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json" },
    body: "{}",
  });
  const cfg = (await response.json()) as CheckoutBootConfig & { message?: string };
  if (!response.ok) {
    root.textContent = cfg.message ?? "MainMoney is unavailable.";
    return;
  }
  const session = createSession({
    merchantBackendUrl: cfg.merchantBackendUrl,
    clientToken: cfg.clientToken,
    locale: cfg.locale ?? "en",
  });
  const checkout = createCheckout(session, {
    operation: "deposit",
    pollUrl: cfg.pollUrl,
    pollHeaders: cfg.pollHeaders,
    amount: cfg.amount ?? undefined,
    lockAmount: cfg.lockAmount === true,
    reference: cfg.reference,
  });
  await checkout.loadCountries();
  mountCheckout(root, checkout, plugin.logoUrl !== undefined ? { logoUrl: plugin.logoUrl } : {});
  let submitted = false;
  checkout.subscribe((state) => {
    if (submitted || state.step !== "terminal") {
      return;
    }
    if ((state.status?.status ?? "").toUpperCase() === "SUCCESS") {
      submitted = true;
      root.dispatchEvent(new CustomEvent("mm-aggr:success", { bubbles: true }));
      placeOrder();
    }
  });
}

function scan(): void {
  const plugin = window.mmAggrConfig;
  if (plugin === undefined) {
    return;
  }
  document.querySelectorAll<HTMLElement>(SELECTOR).forEach((root) => {
    if (root.hasAttribute(MOUNTED)) {
      return;
    }
    root.setAttribute(MOUNTED, "1");
    mountOne(root, plugin).catch(() => {
      root.textContent = "MainMoney is unavailable.";
    });
  });
}

function boot(): void {
  scan();
  // WooCommerce replaces the payment box on `update_checkout` and Blocks renders it lazily.
  new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}

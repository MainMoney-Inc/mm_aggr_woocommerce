(() => {
  // assets/js/order-status.ts
  var SLOW_AFTER_MS = 3e4;
  var FAST_INTERVAL_MS = 1e3;
  var SLOW_INTERVAL_MS = 3e3;
  var GIVE_UP_AFTER_MS = 10 * 6e4;
  function mount(root) {
    const { url, key, logo, waiting, slow, failed, retry, mismatch } = root.dataset;
    if (!url || !key) {
      return;
    }
    root.innerHTML = "";
    const box = document.createElement("div");
    box.className = "mm-confirm";
    const spinner = document.createElement("div");
    spinner.className = "mm-spinner";
    for (let i = 0; i < 2; i += 1) {
      const curve = document.createElement("div");
      curve.className = "mm-spinner-curve";
      spinner.append(curve);
    }
    if (logo) {
      const img = document.createElement("img");
      img.className = "mm-spinner-logo";
      img.alt = "MainMoney";
      img.src = logo;
      spinner.append(img);
    }
    const caption = document.createElement("div");
    caption.textContent = waiting ?? "";
    const notice = document.createElement("div");
    notice.className = "mm-confirm-slow";
    box.append(spinner, caption, notice);
    root.append(box);
    const started = Date.now();
    const finish = (text, retryUrl) => {
      box.innerHTML = "";
      const message = document.createElement("div");
      message.textContent = text;
      box.append(message);
      if (retryUrl && retry) {
        const link = document.createElement("a");
        link.href = retryUrl;
        link.className = "button";
        link.textContent = retry;
        box.append(link);
      }
    };
    const tick = async () => {
      const elapsed = Date.now() - started;
      const next = elapsed >= SLOW_AFTER_MS ? SLOW_INTERVAL_MS : FAST_INTERVAL_MS;
      try {
        const response = await fetch(`${url}?key=${encodeURIComponent(key)}`, { credentials: "same-origin", cache: "no-store" });
        if (response.ok) {
          const body = await response.json();
          if (body.state === "paid") {
            window.location.reload();
            return;
          }
          if (body.state === "failed") {
            finish(failed ?? "", body.retry_url);
            return;
          }
          if (body.state === "mismatch") {
            finish(mismatch ?? "");
            return;
          }
        }
      } catch {
      }
      if (elapsed >= SLOW_AFTER_MS) {
        notice.textContent = slow ?? "";
      }
      if (elapsed >= GIVE_UP_AFTER_MS) {
        return;
      }
      window.setTimeout(() => void tick(), next);
    };
    void tick();
  }
  document.querySelectorAll(".mm-aggr-order-status").forEach(mount);
})();

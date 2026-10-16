(function (window) {
  var registry = window.wc && window.wc.wcBlocksRegistry;
  var element = window.wp && window.wp.element;
  if (!registry || !element) {
    return;
  }
  var createElement = element.createElement;
  var settings = (window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting("mm_aggr_data", {})) || {};
  var label = createElement(
    "span",
    { style: { display: "inline-flex", alignItems: "center", gap: "8px" } },
    settings.icon ? createElement("img", { src: settings.icon, alt: "MainMoney", style: { height: "24px", width: "24px" } }) : null,
    settings.title || "MainMoney"
  );
  registry.registerPaymentMethod({
    name: "mm_aggr",
    label: label,
    ariaLabel: "MainMoney",
    canMakePayment: function () {
      return true;
    },
    content: createElement("div", { className: "mm-aggr-checkout", id: "mm-aggr-blocks-checkout" }),
    edit: createElement("div", { id: "mm-aggr-blocks-checkout-edit" }),
    supports: { features: ["products", "refunds"] },
  });
})(window);

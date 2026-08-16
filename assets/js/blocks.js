(function (window) {
  var registry = window.wc && window.wc.wcBlocksRegistry;
  var element = window.wp && window.wp.element;
  if (!registry || !element) {
    return;
  }
  var createElement = element.createElement;
  registry.registerPaymentMethod({
    name: "mm_aggr",
    label: "MainMoney",
    ariaLabel: "MainMoney",
    canMakePayment: function () {
      return true;
    },
    content: createElement("div", { id: "mm-aggr-blocks-checkout" }),
    edit: createElement("div", { id: "mm-aggr-blocks-checkout-edit" }),
    supports: { features: ["products", "refunds"] },
  });
})(window);

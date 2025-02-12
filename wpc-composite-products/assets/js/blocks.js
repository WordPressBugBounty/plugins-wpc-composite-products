const {registerCheckoutFilters} = window.wc.blocksCheckout;

const modifyCartItemClass = (defaultValue, extensions, args) => {
    if (args?.cartItem.wooco_composite) {
        defaultValue += ' wooco-composite';
    }

    if (args?.cartItem.wooco_component) {
        defaultValue += ' wooco-component';
    }

    if (args?.cartItem.wooco_hide_component) {
        defaultValue += ' wooco-hide-component';
    }

    return defaultValue;
};

registerCheckoutFilters('wooco-blocks', {
    cartItemClass: modifyCartItemClass,
});
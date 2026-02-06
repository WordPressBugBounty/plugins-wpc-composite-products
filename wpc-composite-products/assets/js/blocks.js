const woocoCheckoutFilters = window.wc.blocksCheckout.registerCheckoutFilters;

const woocoCartItemClass = (defaultValue, extensions, args) => {
    const isCartContext = args?.context === 'cart' || args?.context === 'summary';

    if (!isCartContext) {
        return defaultValue;
    }

    if (args?.cartItem?.wooco_composite) {
        defaultValue += ' wooco-composite';
    }

    if (args?.cartItem?.wooco_component) {
        defaultValue += ' wooco-component';
    }

    if (args?.cartItem?.wooco_hide_component) {
        defaultValue += ' wooco-hide-component';
    }

    return defaultValue;
};

const woocoShowRemoveItemLink = (defaultValue, extensions, args) => {
    const isCartContext = args?.context === 'cart';

    if (!isCartContext) {
        return defaultValue;
    }

    if (args?.cartItem?.wooco_component) {
        return false;
    }

    return defaultValue;
};

const woocoCartItemPrice = (defaultValue, extensions, args, validation) => {
    const isCartContext = args?.context === 'cart' || args?.context === 'summary';

    if (!isCartContext) {
        return defaultValue;
    }

    if (args?.cartItem?.wooco_composite && args?.cartItem?.wooco_price) {
        return wooco_format_price(args?.cartItem?.wooco_price * args?.cartItem?.quantity).replace(/<[^>]*>?/gm, '') + '<price/>';
    }

    return '<price/>';
};

const woocoSubtotalPriceFormat = (defaultValue, extensions, args, validation) => {
    const isCartContext = args?.context === 'cart' || args?.context === 'summary';

    if (!isCartContext) {
        return defaultValue;
    }

    if (args?.cartItem?.wooco_composite && args?.cartItem?.wooco_price) {
        return wooco_format_price(args?.cartItem?.wooco_price).replace(/<[^>]*>?/gm, '') + '<price/>';
    }

    return '<price/>';
};

woocoCheckoutFilters('wooco-blocks', {
    cartItemClass: woocoCartItemClass,
    showRemoveItemLink: woocoShowRemoveItemLink,
    cartItemPrice: woocoCartItemPrice,
    subtotalPriceFormat: woocoSubtotalPriceFormat
});
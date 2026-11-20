import type { LeadProduct } from "@/types/lead-product";

const quantity = new Intl.NumberFormat("vi-VN", { maximumFractionDigits: 3 });
const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 2,
});

export default function LeadProductList({ products }: { products: LeadProduct[] }) {
    if (!products.length) return null;

    return (
        <ul className="leads-product-list">
            {products.map((product) => (
                <li key={product.key}>
                    {product.name}
                    {product.sku && ` · ${product.sku}`}
                    {product.quantity !== null && product.unit_price !== null &&
                        ` · ${quantity.format(Number(product.quantity))} × ${money.format(Number(product.unit_price))}`}
                </li>
            ))}
        </ul>
    );
}

import type { FormInstance } from "antd";
import { router } from "@inertiajs/react";

type Method = "post" | "put" | "patch" | "delete";

export function submitForm(
    form: FormInstance,
    method: Method,
    url: string,
    data: Record<string, unknown>,
    opts: { onSuccess?: () => void; onFinish?: () => void } = {},
) {
    router[method](url, data as never, {
        preserveScroll: true,
        onSuccess: () => opts.onSuccess?.(),
        onError: (errors: Record<string, string>) => {
            form.setFields(
                Object.entries(errors).map(([name, message]) => ({
                    name: name
                        .split(".")
                        .map((p) => (/^\d+$/.test(p) ? Number(p) : p)),
                    errors: [message],
                })),
            );
            const first = Object.keys(errors)[0];
            if (first)
                form.scrollToField(first.split(".")[0], { block: "center" });
        },
        onFinish: () => opts.onFinish?.(),
    });
}

export function emptyToNull<T extends Record<string, unknown>>(values: T): T {
    return Object.fromEntries(
        Object.entries(values).map(([k, v]) => [
            k,
            v === "" || v === undefined ? null : v,
        ]),
    ) as T;
}

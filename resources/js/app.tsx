import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { App as AntApp, ConfigProvider } from "antd";
import viVN from "antd/locale/vi_VN";
import dayjs from "dayjs";
import "dayjs/locale/vi";

dayjs.locale("vi");

createInertiaApp({
    title: (title) => (title ? `${title} – D&T CRM` : "D&T CRM"),
    resolve: (name) => {
        const pages = import.meta.glob("./pages/**/*.tsx", { eager: true });
        return pages[`./pages/${name}.tsx`] as never;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ConfigProvider
                locale={viVN}
                theme={{ token: { colorPrimary: "#1d4ed8", borderRadius: 8 } }}
            >
                <AntApp>
                    <App {...props} />
                </AntApp>
            </ConfigProvider>,
        );
    },
});

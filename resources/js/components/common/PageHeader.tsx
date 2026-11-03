import type { ReactNode } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeftOutlined } from "@ant-design/icons";

interface Props {
    title: ReactNode;
    subtitle?: ReactNode;
    extra?: ReactNode;
    back?: { href: string; label: string };
}

export default function PageHeader({ title, subtitle, extra, back }: Props) {
    return (
        <header className="crm-page-header">
            {back && (
                <Link href={back.href} className="crm-page-header__back">
                    <ArrowLeftOutlined aria-hidden="true" />
                    <span>{back.label}</span>
                </Link>
            )}

            <div className="crm-page-header__row">
                <div className="crm-page-header__text">
                    <h1 className="crm-page-header__title">{title}</h1>

                    {subtitle != null && (
                        <div className="crm-page-header__subtitle">
                            {subtitle}
                        </div>
                    )}
                </div>

                {extra != null && (
                    <div className="crm-page-header__extra">{extra}</div>
                )}
            </div>
        </header>
    );
}

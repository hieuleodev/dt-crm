import { Progress, Tooltip } from "antd";

export default function CompletenessBadge({
    value,
    missing,
}: {
    value: number;
    missing?: string[];
}) {
    const color = value < 60 ? "#cf1322" : value < 90 ? "#d48806" : "#389e0d";
    const bar = (
        <Progress
            percent={value}
            size="small"
            strokeColor={color}
            style={{ minWidth: 96, margin: 0 }}
        />
    );

    return missing?.length ? (
        <Tooltip title={`Còn thiếu: ${missing.join(", ")}`}>{bar}</Tooltip>
    ) : (
        bar
    );
}

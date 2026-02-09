import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tone = 'primary' | 'teal' | 'amber' | 'success' | 'danger';

const toneClasses: Record<Tone, string> = {
    primary: 'tone-primary',
    teal: 'tone-teal',
    amber: 'tone-amber',
    success: 'tone-success',
    danger: 'tone-danger',
};

type Props = {
    label: string;
    value: string;
    delta?: string;
    note?: string;
    tone?: Tone;
    icon: LucideIcon;
};

export function MetricCard({
    label,
    value,
    delta,
    note,
    tone = 'primary',
    icon: Icon,
}: Props) {
    return (
        <Card className="py-4">
            <CardContent className="flex items-center justify-between gap-4">
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-2xl font-semibold tracking-tight">
                            {value}
                        </p>
                        {delta ? (
                            <Badge
                                variant="secondary"
                                className="h-5 px-1.5 text-[11px]"
                            >
                                {delta}
                            </Badge>
                        ) : null}
                    </div>
                    {note ? (
                        <p className="text-xs text-muted-foreground">{note}</p>
                    ) : null}
                </div>
                <div
                    className={cn(
                        'flex size-12 items-center justify-center rounded-xl',
                        toneClasses[tone],
                    )}
                >
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

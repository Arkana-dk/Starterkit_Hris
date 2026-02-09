import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';

type PageProps = {
    employee: {
        name: string;
        employee_code: string;
        company?: string | null;
    };
    shift: {
        name: string;
        start_time: string;
        end_time: string;
        grace_minutes: number;
        is_overnight: boolean;
    } | null;
    workLocation: {
        name: string;
        latitude: number;
        longitude: number;
        radius_meters: number;
    } | null;
    log: {
        id: number;
        work_date: string;
        check_in_at?: string | null;
        check_out_at?: string | null;
        approval_status?: string | null;
        status?: string | null;
        check_in_distance_meters?: number | null;
        check_out_distance_meters?: number | null;
        photos?: Array<{
            id: number;
            type: 'check_in' | 'check_out';
            file_path: string;
        }>;
    } | null;
    canCheckIn: boolean;
    canCheckOut: boolean;
    serverTime: string;
};

const formatTime = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

export default function EmployeeAttendanceIndex() {
    const { employee, shift, workLocation, log, canCheckIn, canCheckOut, serverTime } =
        usePage<PageProps>().props;
    const [loadingLocation, setLoadingLocation] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        latitude: '',
        longitude: '',
        photo: null as File | null,
    });

    const fetchLocation = () => {
        if (!navigator.geolocation) {
            alert('Geolocation tidak didukung di browser ini.');
            return;
        }

        setLoadingLocation(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setData('latitude', position.coords.latitude.toString());
                setData('longitude', position.coords.longitude.toString());
                setLoadingLocation(false);
            },
            () => {
                alert('Tidak bisa mengambil lokasi. Pastikan izin lokasi aktif.');
                setLoadingLocation(false);
            },
        );
    };

    const submitCheckIn = () => {
        post('/employee/attendance/check-in', {
            forceFormData: true,
            onSuccess: () => reset('photo'),
        });
    };

    const submitCheckOut = () => {
        post('/employee/attendance/check-out', {
            forceFormData: true,
            onSuccess: () => reset('photo'),
        });
    };

    const checkInPhoto = useMemo(
        () => log?.photos?.find((photo) => photo.type === 'check_in'),
        [log?.photos],
    );
    const checkOutPhoto = useMemo(
        () => log?.photos?.find((photo) => photo.type === 'check_out'),
        [log?.photos],
    );

    return (
        <AppLayout>
            <Head title="My Attendance" />

            <div className="flex flex-col gap-6 px-6 py-6">
                <section className="rounded-xl border border-border/60 bg-gradient-to-br from-primary/10 via-transparent to-secondary/30 p-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <Badge variant="secondary">My Attendance</Badge>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Halo, {employee.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {employee.employee_code} ·{' '}
                                {employee.company ?? '-'} · {serverTime}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Check In / Check Out</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Latitude</Label>
                                    <Input value={data.latitude} readOnly />
                                </div>
                                <div className="space-y-2">
                                    <Label>Longitude</Label>
                                    <Input value={data.longitude} readOnly />
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={fetchLocation}
                                    disabled={loadingLocation}
                                >
                                    {loadingLocation ? 'Mengambil...' : 'Ambil Lokasi'}
                                </Button>
                                {workLocation && (
                                    <Badge variant="outline">
                                        Lokasi: {workLocation.name}
                                    </Badge>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>Selfie</Label>
                                <Input
                                    type="file"
                                    accept="image/*"
                                    capture="user"
                                    onChange={(event) =>
                                        setData(
                                            'photo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {errors.photo && (
                                    <p className="text-xs text-destructive">
                                        {errors.photo}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={submitCheckIn}
                                    disabled={!canCheckIn || processing}
                                >
                                    {processing ? 'Memproses...' : 'Check In'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={submitCheckOut}
                                    disabled={!canCheckOut || processing}
                                >
                                    {processing ? 'Memproses...' : 'Check Out'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm text-muted-foreground">
                            {shift ? (
                                <>
                                    <p className="text-sm font-medium text-foreground">
                                        {shift.name}
                                    </p>
                                    <p>
                                        {shift.start_time} - {shift.end_time}
                                    </p>
                                    <p>Grace: {shift.grace_minutes} menit</p>
                                </>
                            ) : (
                                <p>Belum ada jadwal hari ini.</p>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm text-muted-foreground">
                            <p>
                                Tanggal:{' '}
                                <span className="text-foreground">
                                    {formatDate(log?.work_date)}
                                </span>
                            </p>
                            <p>
                                Status:{' '}
                                <Badge variant="outline">
                                    {log?.status ?? '-'}
                                </Badge>
                            </p>
                            <p>
                                Approval:{' '}
                                <Badge variant="outline">
                                    {log?.approval_status ?? '-'}
                                </Badge>
                            </p>
                            <p>
                                Check In: {formatTime(log?.check_in_at)}
                            </p>
                            <p>
                                Check Out: {formatTime(log?.check_out_at)}
                            </p>
                            <p>
                                GPS In: {log?.check_in_distance_meters ?? '-'} m
                            </p>
                            <p>
                                GPS Out: {log?.check_out_distance_meters ?? '-'} m
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Selfie Tersimpan</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            {checkInPhoto ? (
                                <a
                                    href={`/storage/${checkInPhoto.file_path}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-lg border border-border/60 px-3 py-2"
                                >
                                    Selfie Check In
                                </a>
                            ) : (
                                <div className="rounded-lg border border-border/60 px-3 py-2">
                                    Belum ada selfie check in.
                                </div>
                            )}
                            {checkOutPhoto ? (
                                <a
                                    href={`/storage/${checkOutPhoto.file_path}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-lg border border-border/60 px-3 py-2"
                                >
                                    Selfie Check Out
                                </a>
                            ) : (
                                <div className="rounded-lg border border-border/60 px-3 py-2">
                                    Belum ada selfie check out.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </AppLayout>
    );
}

import { Head, usePage } from '@inertiajs/react';
import EmployeeForm from './employee-form';

type Option = {
    id: number;
    name?: string;
    title?: string;
};

type ManagerOption = {
    id: number;
    employee_code: string;
    user: {
        name: string;
    } | null;
};

type PageProps = {
    companies: Option[];
    branches: Option[];
    departments: Option[];
    positions: Option[];
    jobLevels: Option[];
    managers: ManagerOption[];
};

export default function EmployeeCreate() {
    const props = usePage<PageProps>().props;

    return (
        <>
            <Head title="Tambah Karyawan" />
            <EmployeeForm mode="create" options={props} />
        </>
    );
}

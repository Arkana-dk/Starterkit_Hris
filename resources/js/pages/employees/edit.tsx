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

type EmployeeDetail = {
    id: number;
    employee_code: string;
    employment_status: string;
    employment_type: string;
    join_date: string;
    confirmation_date?: string | null;
    resign_date?: string | null;
    work_email?: string | null;
    work_phone?: string | null;
    office_location?: string | null;
    user: {
        name: string;
        email: string;
        role?: string | null;
    };
    company?: Option | null;
    branch?: Option | null;
    department?: Option | null;
    position?: Option | null;
    job_level?: Option | null;
    manager?: ManagerOption | null;
    profile?: Record<string, string | null> | null;
    documents?: Array<{
        id: number;
        type: string;
        number?: string | null;
        issued_at?: string | null;
        expires_at?: string | null;
        file_path?: string | null;
    }>;
};

type PageProps = {
    companies: Option[];
    branches: Option[];
    departments: Option[];
    positions: Option[];
    jobLevels: Option[];
    managers: ManagerOption[];
    employee: EmployeeDetail;
};

export default function EmployeeEdit() {
    const { employee, ...options } = usePage<PageProps>().props;

    return (
        <>
            <Head
                title={`Edit Karyawan · ${employee.user?.name ?? employee.employee_code}`}
            />
            <EmployeeForm mode="edit" employee={employee} options={options} />
        </>
    );
}

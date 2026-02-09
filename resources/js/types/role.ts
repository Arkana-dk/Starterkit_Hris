export type UserRole = 'superadmin' | 'admin' | 'employee';

export interface RolePermissions {
    canManageUsers: boolean;
    canManageEmployees: boolean;
    canManageAttendance: boolean;
    canManagePayroll: boolean;
    canViewReports: boolean;
    canManageSettings: boolean;
    canManageRoles: boolean;
}

export const rolePermissions: Record<UserRole, RolePermissions> = {
    superadmin: {
        canManageUsers: true,
        canManageEmployees: true,
        canManageAttendance: true,
        canManagePayroll: true,
        canViewReports: true,
        canManageSettings: true,
        canManageRoles: true,
    },
    admin: {
        canManageUsers: false,
        canManageEmployees: true,
        canManageAttendance: true,
        canManagePayroll: true,
        canViewReports: true,
        canManageSettings: false,
        canManageRoles: false,
    },
    employee: {
        canManageUsers: false,
        canManageEmployees: false,
        canManageAttendance: false,
        canManagePayroll: false,
        canViewReports: false,
        canManageSettings: false,
        canManageRoles: false,
    },
};

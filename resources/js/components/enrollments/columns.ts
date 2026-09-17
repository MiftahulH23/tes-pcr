import type { FilterableColumn, FilterOperator } from '@/types/enrollment';

export interface ColumnDef {
    key: FilterableColumn;
    label: string;
    sortable: boolean;
}

/** Table columns in display order — must mirror the backend whitelist in EnrollmentFilters::COLUMNS. */
export const ENROLLMENT_COLUMNS: ColumnDef[] = [
    { key: 'student_nim', label: 'NIM', sortable: true },
    { key: 'student_name', label: 'Nama Mahasiswa', sortable: true },
    { key: 'course_code', label: 'Kode MK', sortable: true },
    { key: 'course_name', label: 'Nama MK', sortable: true },
    { key: 'semester', label: 'Semester', sortable: true },
    { key: 'academic_year', label: 'Tahun Ajaran', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

/** Operators available per column in the advanced filter builder, matching EnrollmentFilters::applyCondition. */
export const OPERATORS_BY_COLUMN: Record<FilterableColumn, { value: FilterOperator; label: string }[]> = {
    student_nim: [
        { value: 'contains', label: 'Mengandung' },
        { value: 'startsWith', label: 'Diawali' },
        { value: 'equal', label: 'Sama dengan' },
    ],
    student_name: [
        { value: 'contains', label: 'Mengandung' },
        { value: 'startsWith', label: 'Diawali' },
        { value: 'equal', label: 'Sama dengan' },
    ],
    course_code: [
        { value: 'contains', label: 'Mengandung' },
        { value: 'startsWith', label: 'Diawali' },
        { value: 'equal', label: 'Sama dengan' },
    ],
    course_name: [
        { value: 'contains', label: 'Mengandung' },
        { value: 'startsWith', label: 'Diawali' },
        { value: 'equal', label: 'Sama dengan' },
    ],
    semester: [
        { value: 'equal', label: 'Sama dengan' },
        { value: 'in', label: 'Salah satu dari' },
    ],
    academic_year: [
        { value: 'equal', label: 'Sama dengan' },
        { value: 'between', label: 'Antara' },
    ],
    status: [
        { value: 'equal', label: 'Sama dengan' },
        { value: 'in', label: 'Salah satu dari' },
    ],
};

/** Placeholder shown in the value input, guiding the expected format per operator. */
export function valuePlaceholder(op: FilterOperator): string {
    return {
        contains: 'Nilai...',
        startsWith: 'Nilai...',
        equal: 'Nilai...',
        in: 'Pisahkan dengan koma, mis. DRAFT,APPROVED',
        between: 'mis. 2023/2024,2025/2026',
    }[op];
}

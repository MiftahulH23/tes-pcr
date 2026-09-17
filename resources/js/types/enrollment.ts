export type Semester = 'GANJIL' | 'GENAP';

export type EnrollmentStatus = 'DRAFT' | 'SUBMITTED' | 'APPROVED' | 'REJECTED';

export interface Enrollment {
    id: number;
    student_nim: string;
    student_name: string;
    course_code: string;
    course_name: string;
    semester: Semester;
    academic_year: string;
    status: EnrollmentStatus;
}

export interface PageMeta {
    page: number;
    page_size: number;
    total: number;
    last_page: number;
}

/** Columns exposed to search/sort/filter — must mirror the backend whitelist in EnrollmentFilters. */
export type FilterableColumn = 'student_nim' | 'student_name' | 'course_code' | 'course_name' | 'semester' | 'academic_year' | 'status';

export type FilterOperator = 'contains' | 'startsWith' | 'equal' | 'in' | 'between';

export interface FilterCondition {
    id: string;
    field: FilterableColumn;
    op: FilterOperator;
    value: string;
}

export type FilterLogic = 'and' | 'or';

export interface SortRule {
    field: FilterableColumn;
    dir: 'asc' | 'desc';
}

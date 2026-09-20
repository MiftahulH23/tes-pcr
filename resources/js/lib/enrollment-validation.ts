export interface EnrollmentFormValues {
    student_nim: string;
    student_name: string;
    student_email: string;
    course_code: string;
    course_name: string;
    course_credits: string;
    academic_year: string;
    semester: string;
    status: string;
    /** Create only: reuse a registered student / course, so only the NIM / code is needed. */
    student_existing?: boolean;
    course_existing?: boolean;
}

const NIM_REGEX = /^\d{8,12}$/;
const COURSE_CODE_REGEX = /^[A-Z]{2,4}[0-9]{3}$/;
const ACADEMIC_YEAR_REGEX = /^(\d{4})\/(\d{4})$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Mirrors StoreEnrollmentRequest/UpdateEnrollmentRequest (app/Http/Requests) and
 * lang/id/validation.php so the messages shown here match what the backend would
 * return — this runs first and blocks submission, the backend re-validates regardless.
 */
export function validateEnrollmentForm(values: EnrollmentFormValues, isEdit: boolean): Record<string, string[]> {
    const errors: Record<string, string[]> = {};

    const addError = (field: string, message: string) => {
        errors[field] = [...(errors[field] ?? []), message];
    };

    const studentExisting = !isEdit && Boolean(values.student_existing);
    const courseExisting = !isEdit && Boolean(values.course_existing);

    if (!isEdit) {
        if (!values.student_nim.trim()) {
            addError('student.nim', 'NIM wajib diisi.');
        } else if (!NIM_REGEX.test(values.student_nim.trim())) {
            addError('student.nim', 'NIM harus berupa 8-12 digit angka tanpa spasi.');
        }
    }

    if (!studentExisting && (!isEdit || values.student_name.trim())) {
        if (!values.student_name.trim()) {
            addError('student.name', 'Nama mahasiswa wajib diisi.');
        } else if (values.student_name.trim().length < 3 || values.student_name.trim().length > 100) {
            addError('student.name', 'Nama mahasiswa minimal 3 karakter, maksimal 100 karakter.');
        }
    }

    if (!studentExisting && (!isEdit || values.student_email.trim())) {
        if (!values.student_email.trim()) {
            addError('student.email', 'Email mahasiswa wajib diisi.');
        } else if (!EMAIL_REGEX.test(values.student_email.trim())) {
            addError('student.email', 'Email mahasiswa harus berupa alamat email yang valid.');
        }
    }

    if (!isEdit) {
        if (!values.course_code.trim()) {
            addError('course.code', 'Kode mata kuliah wajib diisi.');
        } else if (!COURSE_CODE_REGEX.test(values.course_code.trim())) {
            addError('course.code', 'Kode mata kuliah harus 2-4 huruf besar diikuti 3 angka, contoh IF101.');
        }
    }

    if (!courseExisting && (!isEdit || values.course_name.trim())) {
        if (!values.course_name.trim()) {
            addError('course.name', 'Nama mata kuliah wajib diisi.');
        } else if (values.course_name.trim().length < 3 || values.course_name.trim().length > 120) {
            addError('course.name', 'Nama mata kuliah minimal 3 karakter, maksimal 120 karakter.');
        }
    }

    if (!courseExisting && (!isEdit || values.course_credits.trim())) {
        if (!values.course_credits.trim()) {
            addError('course.credits', 'SKS wajib diisi.');
        } else {
            const credits = Number(values.course_credits);
            if (!Number.isInteger(credits) || credits < 1 || credits > 6) {
                addError('course.credits', 'SKS harus berupa angka bulat antara 1 dan 6.');
            }
        }
    }

    if (!values.academic_year.trim()) {
        addError('academic_year', 'Tahun ajaran wajib diisi.');
    } else {
        const match = values.academic_year.trim().match(ACADEMIC_YEAR_REGEX);

        if (!match) {
            addError('academic_year', 'Tahun ajaran harus berformat YYYY/YYYY, contoh 2025/2026.');
        } else if (Number(match[2]) !== Number(match[1]) + 1) {
            addError('academic_year', 'Tahun ajaran harus berurutan, contoh 2025/2026.');
        }
    }

    if (!values.semester.trim()) {
        addError('semester', 'Semester wajib diisi.');
    }

    if (!values.status.trim()) {
        addError('status', 'Status wajib diisi.');
    }

    return errors;
}

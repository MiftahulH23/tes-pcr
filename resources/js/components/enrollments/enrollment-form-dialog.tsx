import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { ApiError, apiFetch } from '@/lib/api';
import type { Enrollment, EnrollmentStatus, Semester } from '@/types/enrollment';
import { useEffect, useState, type ReactNode } from 'react';

interface FormValues {
    student_nim: string;
    student_name: string;
    student_email: string;
    course_code: string;
    course_name: string;
    course_credits: string;
    academic_year: string;
    semester: Semester | '';
    status: EnrollmentStatus | '';
}

function SectionLabel({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-center gap-2">
            <span className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{children}</span>
            <Separator className="flex-1" />
        </div>
    );
}

const EMPTY_FORM: FormValues = {
    student_nim: '',
    student_name: '',
    student_email: '',
    course_code: '',
    course_name: '',
    course_credits: '',
    academic_year: '',
    semester: '',
    status: 'DRAFT',
};

interface EnrollmentFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    enrollment: Enrollment | null;
    statuses: EnrollmentStatus[];
    semesters: Semester[];
    onSaved: () => void;
}

export function EnrollmentFormDialog({ open, onOpenChange, enrollment, statuses, semesters, onSaved }: EnrollmentFormDialogProps) {
    const isEdit = enrollment !== null;
    const [values, setValues] = useState<FormValues>(EMPTY_FORM);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setErrors({});
        setValues(
            enrollment
                ? {
                      student_nim: enrollment.student_nim,
                      student_name: enrollment.student_name,
                      student_email: '',
                      course_code: enrollment.course_code,
                      course_name: enrollment.course_name,
                      course_credits: '',
                      academic_year: enrollment.academic_year,
                      semester: enrollment.semester,
                      status: enrollment.status,
                  }
                : EMPTY_FORM,
        );
    }, [open, enrollment]);

    const fieldError = (key: string) => errors[key]?.[0];

    const submit = async () => {
        setSubmitting(true);
        setErrors({});

        const payload = isEdit
            ? {
                  academic_year: values.academic_year,
                  semester: values.semester,
                  status: values.status,
                  student: { name: values.student_name, email: values.student_email || undefined },
                  course: { name: values.course_name, credits: values.course_credits ? Number(values.course_credits) : undefined },
              }
            : {
                  student: { nim: values.student_nim, name: values.student_name, email: values.student_email },
                  course: { code: values.course_code, name: values.course_name, credits: Number(values.course_credits) },
                  academic_year: values.academic_year,
                  semester: values.semester,
                  status: values.status,
              };

        try {
            await apiFetch(isEdit ? route('enrollments.update', enrollment.id) : route('enrollments.store'), {
                method: isEdit ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });

            onSaved();
            onOpenChange(false);
        } catch (err) {
            if (err instanceof ApiError) {
                setErrors(err.errors);
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-3 sm:max-w-xl" onOpenAutoFocus={(e) => e.preventDefault()}>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Ubah KRS' : 'Tambah KRS'}</DialogTitle>
                </DialogHeader>

                <form
                    className="grid max-h-[70vh] gap-3 overflow-y-auto px-1 py-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void submit();
                    }}
                >
                    <SectionLabel>Data Mahasiswa</SectionLabel>

                    <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="student_nim">NIM</Label>
                            <Input
                                id="student_nim"
                                value={values.student_nim}
                                disabled={isEdit}
                                onChange={(e) => setValues((v) => ({ ...v, student_nim: e.target.value }))}
                                placeholder="10200001"
                            />
                            <div className="min-h-4">
                                <InputError message={fieldError('student.nim')} />
                            </div>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="student_name">Nama Mahasiswa</Label>
                            <Input
                                id="student_name"
                                value={values.student_name}
                                onChange={(e) => setValues((v) => ({ ...v, student_name: e.target.value }))}
                            />
                            <div className="min-h-4">
                                <InputError message={fieldError('student.name')} />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="student_email">Email Mahasiswa {isEdit && '(kosongkan bila tidak diubah)'}</Label>
                        <Input
                            id="student_email"
                            type="email"
                            value={values.student_email}
                            onChange={(e) => setValues((v) => ({ ...v, student_email: e.target.value }))}
                            placeholder="mahasiswa@kampus.ac.id"
                        />
                        <div className="min-h-4">
                            <InputError message={fieldError('student.email')} />
                        </div>
                    </div>

                    <SectionLabel>Data Mata Kuliah</SectionLabel>

                    <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-3">
                        <div className="col-span-1 grid gap-1.5">
                            <Label htmlFor="course_code">Kode MK</Label>
                            <Input
                                id="course_code"
                                value={values.course_code}
                                disabled={isEdit}
                                onChange={(e) => setValues((v) => ({ ...v, course_code: e.target.value.toUpperCase() }))}
                                placeholder="IF101"
                            />
                            <div className="min-h-4">
                                <InputError message={fieldError('course.code')} />
                            </div>
                        </div>
                        <div className="col-span-2 grid gap-1.5">
                            <Label htmlFor="course_name">Nama MK</Label>
                            <Input
                                id="course_name"
                                value={values.course_name}
                                onChange={(e) => setValues((v) => ({ ...v, course_name: e.target.value }))}
                            />
                            <div className="min-h-4">
                                <InputError message={fieldError('course.name')} />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="course_credits">SKS {isEdit && '(kosongkan bila tidak diubah)'}</Label>
                        <Input
                            id="course_credits"
                            type="number"
                            min={1}
                            max={6}
                            value={values.course_credits}
                            onChange={(e) => setValues((v) => ({ ...v, course_credits: e.target.value }))}
                        />
                        <div className="min-h-4">
                            <InputError message={fieldError('course.credits')} />
                        </div>
                    </div>

                    <SectionLabel>Data KRS</SectionLabel>

                    <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="academic_year">Tahun Ajaran</Label>
                            <Input
                                id="academic_year"
                                value={values.academic_year}
                                onChange={(e) => setValues((v) => ({ ...v, academic_year: e.target.value }))}
                                placeholder="2025/2026"
                            />
                            <div className="min-h-4">
                                <InputError message={fieldError('academic_year')} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Semester</Label>
                            <Select value={values.semester} onValueChange={(semester: Semester) => setValues((v) => ({ ...v, semester }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih" />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <div className="min-h-4">
                                <InputError message={fieldError('semester')} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Status</Label>
                            <Select value={values.status} onValueChange={(status: EnrollmentStatus) => setValues((v) => ({ ...v, status }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <div className="min-h-4">
                                <InputError message={fieldError('status')} />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={submitting}>
                            {submitting ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

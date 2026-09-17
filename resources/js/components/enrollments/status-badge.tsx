import { Badge } from '@/components/ui/badge';
import type { EnrollmentStatus } from '@/types/enrollment';

const VARIANT_BY_STATUS: Record<EnrollmentStatus, 'secondary' | 'outline' | 'default' | 'destructive'> = {
    DRAFT: 'secondary',
    SUBMITTED: 'outline',
    APPROVED: 'default',
    REJECTED: 'destructive',
};

export function StatusBadge({ status }: { status: EnrollmentStatus }) {
    return <Badge variant={VARIANT_BY_STATUS[status]}>{status}</Badge>;
}

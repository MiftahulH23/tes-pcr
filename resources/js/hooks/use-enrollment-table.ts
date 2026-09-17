import { useCallback, useEffect, useMemo, useState } from 'react';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import type { Enrollment, EnrollmentStatus, FilterCondition, FilterLogic, FilterableColumn, PageMeta, Semester, SortRule } from '@/types/enrollment';

interface EnrollmentApiResponse {
    data: Enrollment[];
    meta: PageMeta;
}

const DEFAULT_PAGE_SIZE = 20;
const SEARCH_DEBOUNCE_MS = 400;

export function useEnrollmentTable() {
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
    const [search, setSearch] = useState('');
    const [quickStatus, setQuickStatus] = useState<EnrollmentStatus[]>([]);
    const [quickSemester, setQuickSemester] = useState<Semester[]>([]);
    const [sorts, setSorts] = useState<SortRule[]>([]);
    const [advancedLogic, setAdvancedLogic] = useState<FilterLogic>('and');
    const [advancedConditions, setAdvancedConditions] = useState<FilterCondition[]>([]);

    const [rows, setRows] = useState<Enrollment[]>([]);
    const [meta, setMeta] = useState<PageMeta>({ page: 1, page_size: DEFAULT_PAGE_SIZE, total: 0, last_page: 1 });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [refreshToken, setRefreshToken] = useState(0);
    const refetch = useCallback(() => setRefreshToken((n) => n + 1), []);

    const debouncedSearch = useDebouncedValue(search, SEARCH_DEBOUNCE_MS);

    // Any filter/sort change (other than page navigation itself) should snap back to page 1.
    useEffect(() => {
        setPage(1);
    }, [debouncedSearch, quickStatus, quickSemester, sorts, advancedLogic, advancedConditions]);

    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('page_size', String(pageSize));

        if (debouncedSearch) {
            params.set('q', debouncedSearch);
        }

        quickStatus.forEach((status) => params.append('status[]', status));
        quickSemester.forEach((semester) => params.append('semester[]', semester));

        if (sorts.length > 0) {
            params.set('sort', JSON.stringify(sorts));
        }

        const activeConditions = advancedConditions.filter((c) => c.value.trim() !== '');
        if (activeConditions.length > 0) {
            params.set(
                'filters',
                JSON.stringify({
                    logic: advancedLogic,
                    conditions: activeConditions.map(({ field, op, value }) => ({
                        field,
                        op,
                        value: op === 'in' || op === 'between' ? value.split(',').map((v) => v.trim()) : value,
                    })),
                }),
            );
        }

        return params.toString();
    }, [page, pageSize, debouncedSearch, quickStatus, quickSemester, sorts, advancedLogic, advancedConditions]);

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        setError(null);

        fetch(`${route('enrollments.data')}?${queryString}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                return response.json() as Promise<EnrollmentApiResponse>;
            })
            .then((json) => {
                setRows(json.data);
                setMeta(json.meta);
            })
            .catch((err: unknown) => {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }

                setError(err instanceof Error ? err.message : 'Gagal memuat data.');
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [queryString, refreshToken]);

    const toggleSort = useCallback((field: FilterableColumn, multi: boolean) => {
        setSorts((current) => {
            const existing = current.find((s) => s.field === field);
            const nextDir = existing?.dir === 'asc' ? 'desc' : existing?.dir === 'desc' ? null : 'asc';

            const withoutField = current.filter((s) => s.field !== field);
            const base = multi ? withoutField : [];

            return nextDir ? [...base, { field, dir: nextDir }] : base;
        });
    }, []);

    return {
        // current state
        page,
        pageSize,
        search,
        quickStatus,
        quickSemester,
        sorts,
        advancedLogic,
        advancedConditions,
        rows,
        meta,
        loading,
        error,
        queryString,

        // setters / actions
        setPage,
        setPageSize,
        setSearch,
        setQuickStatus,
        setQuickSemester,
        toggleSort,
        setAdvancedLogic,
        setAdvancedConditions,
        refetch,
    };
}

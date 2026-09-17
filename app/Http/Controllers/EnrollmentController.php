<?php

namespace App\Http\Controllers;

use App\Actions\Enrollments\CreateEnrollment;
use App\Actions\Enrollments\UpdateEnrollment;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Support\EnrollmentFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('enrollments/index', [
            'statuses' => Enrollment::STATUSES,
            'semesters' => Enrollment::SEMESTERS,
        ]);
    }

    public function data(Request $request): AnonymousResourceCollection
    {
        $page = max((int) $request->input('page', 1), 1);
        $pageSize = min(max((int) $request->input('page_size', 20), 1), 200);

        $query = EnrollmentFilters::fromRequest($request->all());
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query->forPage($page, $pageSize)->get();

        return EnrollmentResource::collection($rows)->additional([
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'last_page' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 1,
            ],
        ]);
    }

    public function store(StoreEnrollmentRequest $request, CreateEnrollment $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'KRS berhasil disimpan.');
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment, UpdateEnrollment $action): RedirectResponse
    {
        $action->handle($enrollment, $request->validated());

        return back()->with('success', 'KRS berhasil diperbarui.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $enrollment->delete();

        return back()->with('success', 'KRS berhasil dihapus.');
    }
}

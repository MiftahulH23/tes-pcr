<?php

namespace App\Http\Controllers;

use App\Actions\Enrollments\CreateEnrollment;
use App\Actions\Enrollments\ExportEnrollmentsCsv;
use App\Actions\Enrollments\UpdateEnrollment;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentDetailResource;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Support\EnrollmentFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $params = $request->all();

        // Counting an unfiltered 3-table join over 5M+ rows is the one query
        // an index can't help with, so skip the join for COUNT when nothing
        // in the request actually needs it (see EnrollmentFilters::needsJoin).
        $total = EnrollmentFilters::fromRequest($params, EnrollmentFilters::needsJoin($params))
            ->toBase()
            ->getCountForPagination();

        $rows = EnrollmentFilters::fromRequest($params, true)->forPage($page, $pageSize)->get();

        return EnrollmentResource::collection($rows)->additional([
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'last_page' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 1,
            ],
        ]);
    }

    public function show(Enrollment $enrollment): EnrollmentDetailResource
    {
        return new EnrollmentDetailResource($enrollment->load(['student', 'course']));
    }

    public function store(StoreEnrollmentRequest $request, CreateEnrollment $action): JsonResponse
    {
        $enrollment = $action->handle($request->validated());

        return response()->json([
            'message' => 'KRS berhasil disimpan.',
            'data' => $enrollment,
        ], 201);
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment, UpdateEnrollment $action): JsonResponse
    {
        $enrollment = $action->handle($enrollment, $request->validated());

        return response()->json([
            'message' => 'KRS berhasil diperbarui.',
            'data' => $enrollment,
        ]);
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();

        return response()->json(['message' => 'KRS berhasil dihapus.'], 200);
    }

    public function export(Request $request, ExportEnrollmentsCsv $action): StreamedResponse
    {
        return $action->handle($request->all());
    }
}

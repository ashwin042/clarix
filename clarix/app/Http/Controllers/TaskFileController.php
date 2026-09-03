<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCompletedFilesRequest;
use App\Http\Requests\UploadTaskFilesRequest;
use App\Models\Task;
use App\Models\TaskFile;
use App\Notifications\CompletedFileUploadedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class TaskFileController extends Controller
{
    public function store(UploadTaskFilesRequest $request, Task $task)
    {
        foreach ($request->file('files') as $file) {
            $path = $file->store($task->storagePrefix(), 'r2');

            // The R2 put cannot join a database transaction, so it happens
            // first; the file record and the unit's storage total then commit
            // together. A failure here leaves an unreferenced object in R2,
            // which the nightly storage:reconcile reports as an orphan.
            DB::transaction(function () use ($task, $file, $path) {
                $task->files()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size'     => $file->getSize(),
                    'mime_type'     => $file->getMimeType(),
                    'uploaded_by'   => auth()->id(),
                ]);
            });
        }

        if ($note = $request->input('upload_note')) {
            $task->notes()->create([
                'note'       => $note,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->back(fallback: route('tasks.index'))->with('success', 'Files uploaded.');
    }

    public function storeCompleted(UploadCompletedFilesRequest $request, Task $task)
    {
        Gate::authorize('uploadCompletedFile', $task);

        foreach ($request->file('files') as $file) {
            $path = $file->store($task->completedStoragePrefix(), 'r2');

            DB::transaction(function () use ($task, $file, $path) {
                $task->files()->create([
                    'file_path'         => $path,
                    'original_name'     => $file->getClientOriginalName(),
                    'file_size'         => $file->getSize(),
                    'mime_type'         => $file->getMimeType(),
                    'uploaded_by'       => auth()->id(),
                    'is_completed_file' => true,
                ]);
            });
        }

        $task->assignments()->update(['status' => 'ready_for_review']);

        if ($task->pm) {
            $task->pm->notify(new CompletedFileUploadedNotification($task));
        }

        if ($task->assignedAdmin) {
            $task->assignedAdmin->notify(new CompletedFileUploadedNotification($task));
        }

        return redirect()->back(fallback: route('tasks.index'))->with('success', 'Completed file uploaded.');
    }

    public function download(Task $task, TaskFile $file)
    {
        abort_if($file->task_id !== $task->id, 404);

        Gate::authorize('downloadFile', [$task, $file]);

        return Storage::disk('r2')->download($file->file_path, $file->original_name);
    }

    /**
     * Every file in the task's Files section as one zip.
     */
    public function downloadAll(Task $task): StreamedResponse
    {
        return $this->streamSectionZip(
            $task,
            $task->regularFiles()->orderBy('id')->get(),
            $task->task_code.'_files.zip',
        );
    }

    /**
     * Every file in the task's Completed Files section as one zip.
     */
    public function downloadAllCompleted(Task $task): StreamedResponse
    {
        return $this->streamSectionZip(
            $task,
            $task->completedFiles()->orderBy('id')->get(),
            $task->task_code.'_completed_files.zip',
        );
    }

    /**
     * Stream one file section to the browser as a zip.
     *
     * The archive is built as the response is written, with each object pulled
     * out of R2 a chunk at a time, so neither the zip nor any single file it
     * holds is ever assembled in memory. Nothing touches the local disk.
     *
     * @param  Collection<int, TaskFile>  $files
     */
    private function streamSectionZip(Task $task, Collection $files, string $zipName): StreamedResponse
    {
        abort_if($files->isEmpty(), 404);

        // Every file in a section carries the same is_completed_file flag, so
        // downloadFile gives one answer for the whole section; the first file
        // stands in for all of them.
        Gate::authorize('downloadFile', [$task, $files->first()]);

        $disk       = Storage::disk('r2');
        $entryNames = $this->uniqueEntryNames($files);

        return response()->streamDownload(function () use ($files, $entryNames, $disk) {
            $zip = new ZipStream(sendHttpHeaders: false);

            foreach ($files as $file) {
                $stream = $disk->readStream($file->file_path);

                // An object missing from R2 — an interrupted upload, a manual
                // delete — drops out of the archive. The response headers have
                // already gone out by now, so there is no status left to fail
                // with, and the rest of the section is still worth sending.
                if (! is_resource($stream)) {
                    continue;
                }

                $zip->addFileFromStream($entryNames[$file->id], $stream);
                fclose($stream);
            }

            $zip->finish();
        }, $zipName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Map each file id to the name it takes inside the archive.
     *
     * Two uploads can share an original name, and a zip entry that repeats an
     * earlier one is what unzip tools silently overwrite. Repeats get a
     * "(2)", "(3)" suffix ahead of the extension, the way a browser
     * disambiguates a repeated download.
     *
     * @param  Collection<int, TaskFile>  $files
     * @return array<int, string>
     */
    private function uniqueEntryNames(Collection $files): array
    {
        $names = [];
        $taken = [];

        foreach ($files as $file) {
            $name      = basename(str_replace('\\', '/', $file->original_name));
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $stem      = pathinfo($name, PATHINFO_FILENAME);
            $suffix    = $extension === '' ? '' : '.'.$extension;

            $candidate = $name;
            $attempt   = 1;

            while (isset($taken[strtolower($candidate)])) {
                $attempt++;
                $candidate = $stem.' ('.$attempt.')'.$suffix;
            }

            $taken[strtolower($candidate)] = true;
            $names[$file->id]              = $candidate;
        }

        return $names;
    }

    public function destroy(Task $task, TaskFile $file)
    {
        Gate::authorize('deleteFile', $task);

        Storage::disk('r2')->delete($file->file_path);

        // The record and the unit's storage total come off together.
        DB::transaction(fn () => $file->delete());

        return redirect()->route('tasks.show', $task)->with('success', 'File deleted.');
    }
}

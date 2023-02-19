<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        if ($request->isMethod('post')) {
            $accessToken = session()->get('google_access_token');

            if (!$accessToken) {
                return redirect()->route('google')->with('error', 'You need to authenticate with Google Drive first.');
            }

            $file = $request->file('file');

            // Get the authenticated user's Google ID
            $userId = auth()->user()->google_id;

            $response = $this->uploadFileToGoogle($file, $userId, $accessToken);

            if (is_array($response)) {
                return redirect()->back()->with('success', 'File uploaded successfully');
            }
        }

        return view('upload');
    }

    protected function uploadFileToGoogle($uploadedFile, $userId, $accessToken)
    {
        $fileName = $uploadedFile->getClientOriginalName();
        $fileContents = file_get_contents($uploadedFile->getRealPath());

        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setAccessToken($accessToken);
        $client->setScopes(['https://www.googleapis.com/auth/drive.file']);
        $client->setSubject($userId);

        $folderName = 'Laravel Uploads';

        $driveService = new Drive($client);

        if (is_null($driveService)) {
            dd('Is null');
        }

        $folderId = null;
        $pageToken = null;

        do {
            $response = $driveService->files->listFiles([
                'q' => "mimeType='application/vnd.google-apps.folder' and trashed = false and name='{$folderName}' and '{$userId}' in owners",
                'spaces' => 'drive',
                'fields' => 'nextPageToken, files(id, name)',
                'pageToken' => $pageToken,
            ]);
            foreach ($response->files as $file) {
                $folderId = $file->id;
                break;
            }
            $pageToken = $response->nextPageToken;
        } while ($pageToken != null);

        if (!$folderId) {
            $folderMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);
            $folder = $driveService->files->create($folderMetadata, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);
            $folderId = $folder->id;
        }

        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
            'description' => 'File created from '.config('app.name'),
        ]);
        $file = $driveService->files->create($fileMetadata, [
            'data' => $fileContents,
            'mimeType' => $uploadedFile->getMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        return [
            'message' => 'File uploaded successfully to Google Drive!',
            'file_id' => $file->id,
        ];
    }
}

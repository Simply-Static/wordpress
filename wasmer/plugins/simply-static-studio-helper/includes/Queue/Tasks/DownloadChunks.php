<?php

namespace Simply_Static_Studio\Queue\Tasks;

use SimplePie\Exception;
use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\File;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\DownloadChunkContinueException;
use Simply_Static_Studio\Queue\Exceptions\DownloadChunkException;

class DownloadChunks extends MigrationTask {

	protected $longRunning = true;

	public function migrate() {
		$fileUrl =  File::getFileUrl();
		$filePath = trailingslashit( File::getDownloadFolder() ) . File::getFileName();

		Migration::setStatus( "Downloading the migration file..." );

		Migration::saveMigrationFilePath( $filePath );
		$chunk = 100 * 1024 * 1024; // 100 MB.

		while( ! $this->downloadFileWithResume( $fileUrl, $filePath, $chunk ) ) {
			sleep( 1 ); // Clear some resources.
		}

		$this->done = true;

		Migration::setStatus( "File Downloaded" );

		return true;
	}

	public function downloadFileWithResume($url, $localPath, $chunkSize = 1024 * 1024) {
		// Check if file already exists to determine if this is a resume
		$fileSize = 0;
		$resumeDownload = false;

		if (file_exists($localPath)) {
			$fileSize = filesize($localPath);
			$resumeDownload = true;
			Logger::log( "Resuming download from byte: $fileSize");
		} else {
			Logger::log( "Starting new download: $url. Saving to: $localPath");
		}

		// Initialize cURL session
		$ch = curl_init();

		// Set cURL options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);

		// Execute the request to get headers
		$response = curl_exec($ch);

		// Check for cURL errors
		if (curl_errno($ch)) {
			Logger::log( "cURL Error: " . curl_error($ch) );
			curl_close($ch);
			throw new DownloadChunkException( "Curl error enccountered." );
		}

		// Get the remote file size from Content-Length header
		$remoteFileSize = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

		// Close the header request
		curl_close($ch);

		// Check if we've already downloaded the complete file
		if ($resumeDownload && $fileSize >= $remoteFileSize) {
			Logger::log( "File already completely downloaded." );
			return true;
		}

		// Open the local file for writing (append mode if resuming)
		$fileHandle = fopen($localPath, $resumeDownload ? "a" : "w");

		if (!$fileHandle) {
			throw new DownloadChunkException(  "Failed to open local file for writing." );
		}

		// Initialize a new cURL session for the actual download
		$ch = curl_init();

		// Set the starting position for the download
		if ($resumeDownload) {
			curl_setopt($ch, CURLOPT_RANGE, "$fileSize-");
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (
			$fileHandle,
			&$fileSize,
			$remoteFileSize
		) {
			$dataSize = strlen($data);
			$fileSize += $dataSize;
			fwrite($fileHandle, $data);

			// Calculate and display progress
			$progress = $remoteFileSize > 0 ? round(($fileSize / $remoteFileSize) * 100, 2) : 0;
			Logger::log( "Downloading... {$fileSize} / {$remoteFileSize} bytes ({$progress}%)" );

			return $dataSize;
		});

		// Execute the request
		$success = curl_exec($ch);

		// Check for cURL errors
		if (curl_errno($ch)) {
			curl_close($ch);
			fclose($fileHandle);

			if ( CURLE_OPERATION_TIMEDOUT === curl_errno($ch) || CURLE_OPERATION_TIMEDOUT === curl_errno($ch) ) {
				// Timed out for some reason, maybe memory. Continue on next iteration.
				throw new DownloadChunkContinueException("Operation timed out.");
			}
			throw new DownloadChunkException( "cURL error: " . curl_error($ch) );
		}

		// Close resources
		curl_close($ch);
		fclose($fileHandle);

		Logger::log( "Download completed successfully!" );
		return true;
	}
}
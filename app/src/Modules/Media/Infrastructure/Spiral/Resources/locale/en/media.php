<?php

declare(strict_types=1);

return [
    'app.media.not_found' => 'Media not found.',
    'app.media.access_denied' => 'You do not have access to this media.',
    'app.media.not_ready' => 'Media is not ready yet.',
    'app.media.conversion_not_found' => 'The requested conversion is missing.',
    'app.media.cannot_make_permanent' => 'Only uploaded or ready media can be made permanent.',
    'app.media.original_not_removable' => 'The original can only be removed from ready media.',
    'app.media.no_conversions_to_keep' => 'Cannot remove the original: the media has no conversions.',
    'app.media.upload_not_pending' => 'Media upload is not awaiting confirmation.',
    'app.media.multipart_upload_not_found' => 'No multipart upload was found for the media.',
    'app.media.uploaded_object_mismatch' => 'The uploaded object is missing or its size does not match the declared one.',
    'app.media.conversion_dimensions_out_of_range' => 'Conversion width and height are out of the allowed range.',
    'app.media.conversion_bitrate_out_of_range' => 'Conversion bitrate is out of the allowed range.',
    'app.media.conversion_sample_rate_out_of_range' => 'Conversion sample rate is out of the allowed range.',
    'app.media.conversion_waveform_peaks_out_of_range' => 'Waveform peak count is out of the allowed range.',
    'app.media.conversion_plan_type_mismatch' => 'Conversion profiles do not match the media type.',
    'app.media.conversion_duplicate_type' => 'Conversion profiles contain a duplicate type.',
    'app.media.video_conversion_profile_required' => 'Video requires exactly one conversion profile.',
    'app.media.audio_conversion_profile_required' => 'Audio requires exactly one conversion profile.',
    'app.media.file_size_exceeded' => 'File size exceeds the allowed limit.',
    'app.media.file_name_without_extension' => 'File name must contain an extension.',
    'app.media.mime_not_allowed' => 'MIME type "{mimeType}" is not allowed by the upload specification.',
    'app.media.unsupported_file_type' => 'File type "{type}" is not supported for upload.',
];

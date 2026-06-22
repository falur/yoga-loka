<?php

declare(strict_types=1);

return [
    'app.media.not_found' => 'Медиа не найдено.',
    'app.media.access_denied' => 'Нет доступа к этому медиа.',
    'app.media.not_ready' => 'Медиа ещё не готово.',
    'app.media.conversion_not_found' => 'Запрошенное преобразование отсутствует.',
    'app.media.cannot_make_permanent' => 'Постоянным можно сделать только загруженное или готовое медиа.',
    'app.media.upload_not_pending' => 'Загрузка медиа не ожидает подтверждения.',
    'app.media.multipart_upload_not_found' => 'Для медиа не найдена multipart-загрузка.',
    'app.media.uploaded_object_mismatch' => 'Загруженный объект отсутствует или его размер не совпадает с заявленным.',
    'app.media.conversion_dimensions_out_of_range' => 'Ширина и высота преобразования вне допустимого диапазона.',
    'app.media.conversion_bitrate_out_of_range' => 'Битрейт преобразования вне допустимого диапазона.',
    'app.media.conversion_sample_rate_out_of_range' => 'Частота дискретизации преобразования вне допустимого диапазона.',
    'app.media.conversion_waveform_peaks_out_of_range' => 'Количество пиков волны вне допустимого диапазона.',
    'app.media.conversion_plan_type_mismatch' => 'Профили преобразования не соответствуют типу медиа.',
    'app.media.conversion_duplicate_type' => 'Профили преобразования содержат повторяющийся тип.',
    'app.media.video_conversion_profile_required' => 'Для видео нужен ровно один профиль преобразования.',
    'app.media.audio_conversion_profile_required' => 'Для аудио нужен ровно один профиль преобразования.',
    'app.media.file_size_exceeded' => 'Размер файла превышает допустимый предел.',
    'app.media.file_name_without_extension' => 'Имя файла должно содержать расширение.',
    'app.media.mime_not_allowed' => 'MIME-тип «{mimeType}» не разрешён спецификацией загрузки.',
    'app.media.unsupported_file_type' => 'Тип файла «{type}» не поддерживается для загрузки.',
];

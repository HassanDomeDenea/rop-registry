<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string|null $caption
 * @property string|null $original_path
 * @property string|null $original_mime_type
 * @property int|null $original_size
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Patient $patient
 */
class Attachment extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['visit_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'hash', 'caption', 'original_path', 'original_mime_type', 'original_size', 'edited_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Determine whether the attachment can be previewed as an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Determine whether the picture shown is an edited version of the one received.
     */
    public function isEdited(): bool
    {
        return $this->original_path !== null;
    }

    /**
     * Get the name to give the file, whose type may have changed when it was edited.
     */
    public function fileName(): string
    {
        return $this->isEdited()
            ? pathinfo($this->original_name, PATHINFO_FILENAME).'.'.pathinfo($this->path, PATHINFO_EXTENSION)
            : $this->original_name;
    }

    public function auditLabel(): string
    {
        return $this->original_name;
    }

    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }

    /**
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return ['id', 'patient_id', 'created_at', 'updated_at', 'deleted_at', 'disk', 'path', 'hash', 'original_path', 'original_mime_type', 'original_size'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Resources;

use App\Models\JobSkill;
use App\Models\SkillMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SkillMatch
 */
class SkillMatchResource extends JsonResource
{
    /**
     * @var array<int, JobSkill>|null
     */
    protected ?array $jobSkillsMap = null;

    /**
     * @var array<int, int>|null
     */
    protected ?array $userSkillIds = null;

    /**
     * Set contextual preloaded job skills map.
     *
     * @param  array<int, JobSkill>  $map
     */
    public function withJobSkillsMap(array $map): self
    {
        $this->jobSkillsMap = $map;

        return $this;
    }

    /**
     * Set contextual preloaded user skill IDs.
     *
     * @param  array<int, int>  $ids
     */
    public function withUserSkillIds(array $ids): self
    {
        $this->userSkillIds = $ids;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSelfDeclared = false;
        if ($this->userSkillIds !== null) {
            $isSelfDeclared = in_array((int) $this->skill_id, $this->userSkillIds, true);
        }

        $matchedCompetency = null;
        if ($this->courseSkill !== null) {
            $matchedCompetency = [
                'id' => $this->courseSkill->id,
                'competency' => $this->courseSkill->competency,
                'evidence' => $this->courseSkill->evidence,
                'source_reference' => $this->courseSkill->source_reference,
                'page' => $this->courseSkill->page,
            ];
        }

        $jobEvidence = [];
        $sourceIds = is_array($this->source_job_skill_ids) ? $this->source_job_skill_ids : [];

        if (! empty($sourceIds)) {
            $jobSkills = [];
            if ($this->jobSkillsMap !== null) {
                foreach ($sourceIds as $sId) {
                    if (isset($this->jobSkillsMap[$sId])) {
                        $jobSkills[] = $this->jobSkillsMap[$sId];
                    }
                }
            } else {
                $jobSkills = JobSkill::whereIn('id', $sourceIds)->with('jobPosting')->get();
            }

            foreach ($jobSkills as $js) {
                $jobEvidence[] = [
                    'job_posting_id' => $js->job_posting_id,
                    'company' => $js->jobPosting?->company ?? '',
                    'evidence' => $js->evidence,
                ];
            }
        }

        return [
            'skill' => $this->skill?->canonical_name ?? '',
            'demand_count' => $this->demand_count,
            'self_declared' => $isSelfDeclared,
            'classification' => $this->classification->value,
            'similarity_score' => $this->similarity_score !== null ? (float) $this->similarity_score : null,
            'reason' => $this->reason,
            'matched_competency' => $matchedCompetency,
            'job_evidence' => $jobEvidence,
        ];
    }
}

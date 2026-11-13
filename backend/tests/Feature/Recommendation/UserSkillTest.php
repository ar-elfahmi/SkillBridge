<?php

declare(strict_types=1);

namespace Tests\Feature\Recommendation;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSkillTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/skills')->assertUnauthorized();
        $this->postJson('/api/me/skills', ['skill' => 'Docker'])->assertUnauthorized();
    }

    public function test_store_normalizes_skill_and_records_reported_as(): void
    {
        $user = $this->user();
        Skill::create(['canonical_name' => 'React', 'aliases' => ['ReactJS']]);

        $this->actingAs($user)
            ->postJson('/api/me/skills', ['skill' => 'ReactJS', 'level' => 'advanced'])
            ->assertCreated()
            ->assertJsonPath('data.skill', 'React')
            ->assertJsonPath('data.reported_as', 'ReactJS')
            ->assertJsonPath('data.level', 'advanced');

        $this->assertDatabaseHas('user_skills', [
            'user_id' => $user->id,
            'reported_as' => 'ReactJS',
        ]);
    }

    public function test_store_is_idempotent(): void
    {
        $user = $this->user();
        Skill::create(['canonical_name' => 'Docker', 'aliases' => []]);

        $this->actingAs($user)->postJson('/api/me/skills', ['skill' => 'Docker'])
            ->assertCreated();

        $this->actingAs($user)->postJson('/api/me/skills', ['skill' => 'docker'])
            ->assertOk()
            ->assertJsonPath('data.skill', 'Docker');

        $this->assertSame(1, UserSkill::where('user_id', $user->id)->count());
    }

    public function test_store_rejects_blank_skill(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/me/skills', ['skill' => '   '])
            ->assertStatus(422);
    }

    public function test_index_only_lists_own_skills(): void
    {
        $mine = $this->user();
        $other = $this->user();
        $skill = Skill::create(['canonical_name' => 'Figma', 'aliases' => []]);

        UserSkill::create(['user_id' => $mine->id, 'skill_id' => $skill->id, 'level' => 'beginner']);
        UserSkill::create(['user_id' => $other->id, 'skill_id' => $skill->id, 'level' => 'advanced']);

        $this->actingAs($mine)->getJson('/api/me/skills')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.level', 'beginner');
    }

    public function test_destroy_only_removes_own_row(): void
    {
        $mine = $this->user();
        $other = $this->user();
        $skill = Skill::create(['canonical_name' => 'Git', 'aliases' => []]);

        $own = UserSkill::create(['user_id' => $mine->id, 'skill_id' => $skill->id, 'level' => 'intermediate']);
        $foreign = UserSkill::create(['user_id' => $other->id, 'skill_id' => $skill->id, 'level' => 'intermediate']);

        $this->actingAs($mine)->deleteJson("/api/me/skills/{$foreign->id}")->assertNotFound();

        $this->actingAs($mine)->deleteJson("/api/me/skills/{$own->id}")->assertNoContent();
        $this->assertDatabaseMissing('user_skills', ['id' => $own->id]);
    }
}
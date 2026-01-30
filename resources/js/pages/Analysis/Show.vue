<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    CheckCircle,
    XCircle,
    AlertTriangle,
    Edit,
    Copy,
    Download,
    RefreshCw,
    Clock,
    Loader2,
    ChevronDown,
    ChevronRight,
    Bot,
    Cpu,
    GitCommit,
} from 'lucide-vue-next';

interface Repository {
    id: number;
    name: string;
    full_name: string;
}

interface Commit {
    sha: string;
    message: string;
}

interface Change {
    sha?: string;
    message?: string;
    description?: string;
}

interface Release {
    id: number;
    from_ref: string;
    to_ref: string;
    recommended_version: string;
    recommended_type: string;
    final_version: string | null;
    final_type: string | null;
    confidence: number;
    status: string;
    release_notes: string;
    commits: Commit[];
    changes: {
        breaking: Change[];
        features: Change[];
        fixes: Change[];
        other: Change[];
    };
    reasoning: string[];
    duration_ms: number;
    created_at: string;
}

interface Stage {
    stage: string;
    status: string;
    duration_ms: number;
    response: Record<string, unknown>;
}

const props = defineProps<{
    repository: Repository;
    release: Release;
    stages: Stage[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const repoId = props.repository?.id;
    const releaseId = props.release?.id;

    const items: BreadcrumbItem[] = [
        { title: 'Repositories', href: '/repositories' },
    ];

    if (repoId !== undefined && repoId !== null) {
        items.push({
            title: props.repository?.name ?? 'Repository',
            href: `/repositories/${repoId}`
        });

        if (releaseId !== undefined && releaseId !== null) {
            items.push({
                title: props.release?.recommended_version ?? 'Release',
                href: `/repositories/${repoId}/releases/${releaseId}`
            });
        }
    }

    return items;
});

// Sections collapse state
const showReasoning = ref(true);
const showBreaking = ref(true);
const showFeatures = ref(false);
const showFixes = ref(false);
const showCommits = ref(false);

// Adjust modal state
const showAdjustModal = ref(false);
const adjustForm = useForm({
    final_version: props.release?.recommended_version ?? '',
    final_type: props.release?.recommended_type ?? '',
    reason_category: '',
    reason_details: '',
});

// Reject modal state
const showRejectModal = ref(false);
const rejectForm = useForm({
    reason_category: '',
    reason_details: '',
});

// Note regeneration
const regenerating = ref(false);

const reasonCategories = [
    { value: 'breaking_missed', label: 'Breaking change missed' },
    { value: 'not_breaking', label: 'Not actually breaking' },
    { value: 'feature_miscategorized', label: 'Feature miscategorized' },
    { value: 'fix_miscategorized', label: 'Fix miscategorized' },
    { value: 'version_wrong', label: 'Wrong version number' },
    { value: 'other', label: 'Other' },
];

const rejectReasons = [
    { value: 'wrong_refs', label: 'Wrong refs selected' },
    { value: 'inaccurate', label: 'Analysis is inaccurate' },
    { value: 'changed_mind', label: 'Changed my mind' },
    { value: 'other', label: 'Other' },
];

const getTypeVariant = (type: string): 'destructive' | 'default' | 'secondary' | 'outline' => {
    switch (type) {
        case 'MAJOR': return 'destructive';
        case 'MINOR': return 'default';
        case 'PATCH': return 'secondary';
        default: return 'outline';
    }
};

const getConfidenceColor = (confidence: number): string => {
    if (confidence >= 85) return 'text-green-600';
    if (confidence >= 70) return 'text-yellow-600';
    return 'text-red-600';
};

const getConfidenceLabel = (confidence: number): string => {
    if (confidence >= 85) return 'High Confidence';
    if (confidence >= 70) return 'Medium Confidence';
    return 'Low Confidence';
};

const getConfidenceExplanation = (confidence: number): { title: string; description: string; action: string } => {
    if (confidence >= 85) {
        return {
            title: 'Strong recommendation',
            description: 'The analysis found clear signals in your commits that strongly indicate this version type.',
            action: 'Safe to accept if the changes align with your expectations.',
        };
    }
    if (confidence >= 70) {
        return {
            title: 'Moderate recommendation',
            description: 'The analysis found some indicators but the commit messages may be ambiguous or mixed.',
            action: 'Review the reasoning and changes before deciding.',
        };
    }
    return {
        title: 'Uncertain recommendation',
        description: 'The analysis couldn\'t find strong signals. Commits may lack conventional prefixes or contain mixed change types.',
        action: 'Carefully review each change and consider adjusting the version.',
    };
};

const getStatusBadge = computed(() => {
    switch (props.release?.status) {
        case 'accepted':
            return { variant: 'default' as const, label: 'Accepted', icon: CheckCircle };
        case 'adjusted':
            return { variant: 'secondary' as const, label: 'Adjusted', icon: Edit };
        case 'rejected':
            return { variant: 'destructive' as const, label: 'Rejected', icon: XCircle };
        default:
            return { variant: 'outline' as const, label: 'Pending', icon: Clock };
    }
});

const isPending = computed(() => props.release?.status === 'pending');

const copyNotes = () => {
    navigator.clipboard.writeText(props.release.release_notes);
};

const downloadNotes = () => {
    const blob = new Blob([props.release.release_notes], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${props.release.recommended_version}-release-notes.md`;
    a.click();
    URL.revokeObjectURL(url);
};

const regenerateNotes = async (style: string) => {
    regenerating.value = true;
    try {
        const response = await fetch(AnalysisController.regenerateNotes(props.repository.id, props.release.id).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ style }),
        });
        if (response.ok) {
            router.reload({ only: ['release'] });
        }
    } finally {
        regenerating.value = false;
    }
};

const acceptRelease = () => {
    router.post(AnalysisController.accept(props.repository.id, props.release.id).url);
};

const submitAdjustment = () => {
    adjustForm.post(AnalysisController.adjust(props.repository.id, props.release.id).url, {
        onSuccess: () => {
            showAdjustModal.value = false;
        },
    });
};

const submitRejection = () => {
    rejectForm.post(AnalysisController.reject(props.repository.id, props.release.id).url, {
        onSuccess: () => {
            showRejectModal.value = false;
        },
    });
};

const getAnalysisSource = computed(() => {
    const aiStage = props.stages?.find(s => s.stage === 'ai_classification');
    if (aiStage?.status === 'skipped') return 'heuristic';
    if (aiStage?.status === 'success') return 'ai';
    return 'heuristic';
});
</script>

<template>
    <div v-if="!release?.id || !repository?.id" class="flex items-center justify-center h-screen">
        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
    <template v-else>
        <Head :title="`${release.recommended_version} - ${repository.name}`" />

        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-5xl">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight">{{ release.recommended_version }}</h1>
                        <Badge :variant="getTypeVariant(release.recommended_type)" class="text-sm">
                            {{ release.recommended_type }}
                        </Badge>
                        <Badge :variant="getStatusBadge.variant">
                            <component :is="getStatusBadge.icon" class="h-3 w-3 mr-1" />
                            {{ getStatusBadge.label }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground mt-1">
                        {{ repository.full_name }} • {{ release.from_ref }} → {{ release.to_ref }}
                    </p>
                </div>

                <!-- Actions -->
                <div v-if="isPending" class="flex gap-2">
                    <Button class="bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/40 dark:hover:bg-red-900/60 dark:text-red-300" @click="showRejectModal = true">
                        <XCircle class="mr-2 h-4 w-4" />
                        Reject
                    </Button>
                    <Button class="bg-amber-100 hover:bg-amber-200 text-amber-700 dark:bg-amber-900/40 dark:hover:bg-amber-900/60 dark:text-amber-300" @click="showAdjustModal = true">
                        <Edit class="mr-2 h-4 w-4" />
                        Adjust
                    </Button>
                    <Button class="bg-green-100 hover:bg-green-200 text-green-700 dark:bg-green-900/40 dark:hover:bg-green-900/60 dark:text-green-300" @click="acceptRelease">
                        <CheckCircle class="mr-2 h-4 w-4" />
                        Accept
                    </Button>
                </div>
            </div>

            <!-- Confidence & Source -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-6">
                            <div>
                                <p class="text-sm text-muted-foreground">Confidence</p>
                                <p :class="['text-3xl font-bold', getConfidenceColor(release.confidence)]">
                                    {{ release.confidence }}%
                                </p>
                                <p class="text-xs text-muted-foreground">{{ getConfidenceLabel(release.confidence) }}</p>
                            </div>
                            <div class="h-12 w-px bg-border" />
                            <div>
                                <p class="text-sm text-muted-foreground">Analysis Source</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <Cpu v-if="getAnalysisSource === 'heuristic'" class="h-5 w-5 text-blue-500" />
                                    <Bot v-else class="h-5 w-5 text-purple-500" />
                                    <span class="font-medium capitalize">{{ getAnalysisSource }} Analysis</span>
                                </div>
                            </div>
                            <div class="h-12 w-px bg-border" />
                            <div>
                                <p class="text-sm text-muted-foreground">Duration</p>
                                <p class="text-lg font-medium">{{ (release.duration_ms / 1000).toFixed(2) }}s</p>
                            </div>
                        </div>

                        <div v-if="release.final_version && release.final_version !== release.recommended_version">
                            <p class="text-sm text-muted-foreground">Final Decision</p>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold">{{ release.final_version }}</span>
                                <Badge :variant="getTypeVariant(release.final_type || '')">
                                    {{ release.final_type }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Confidence Explanation -->
                        <div class="max-w-xs text-right">
                            <p class="text-sm font-medium" :class="getConfidenceColor(release.confidence)">
                                {{ getConfidenceExplanation(release.confidence).title }}
                            </p>
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ getConfidenceExplanation(release.confidence).description }}
                            </p>
                            <p class="text-xs text-muted-foreground mt-1 italic">
                                {{ getConfidenceExplanation(release.confidence).action }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Reasoning -->
            <Card>
                <CardHeader class="cursor-pointer" @click="showReasoning = !showReasoning">
                    <div class="flex items-center justify-between">
                        <CardTitle>Reasoning</CardTitle>
                        <ChevronDown v-if="showReasoning" class="h-5 w-5" />
                        <ChevronRight v-else class="h-5 w-5" />
                    </div>
                </CardHeader>
                <CardContent v-if="showReasoning">
                    <ul class="space-y-2">
                        <li v-for="(reason, idx) in release.reasoning" :key="idx" class="flex items-start gap-2">
                            <CheckCircle class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                            <span>{{ reason }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Change Categories -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Breaking Changes -->
                <Card v-if="release.changes?.breaking?.length > 0" class="border-destructive/50">
                    <CardHeader class="cursor-pointer pb-2" @click="showBreaking = !showBreaking">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <AlertTriangle class="h-5 w-5 text-destructive" />
                                <CardTitle class="text-destructive">Breaking Changes</CardTitle>
                                <Badge variant="destructive">{{ release.changes.breaking.length }}</Badge>
                            </div>
                            <ChevronDown v-if="showBreaking" class="h-5 w-5" />
                            <ChevronRight v-else class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent v-if="showBreaking">
                        <ul class="space-y-2">
                            <li v-for="(change, idx) in release.changes.breaking" :key="idx" class="text-sm">
                                <code v-if="change.sha" class="text-xs text-muted-foreground mr-2">{{ change.sha.slice(0, 7) }}</code>
                                {{ change.description || change.message }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Features -->
                <Card v-if="release.changes?.features?.length > 0">
                    <CardHeader class="cursor-pointer pb-2" @click="showFeatures = !showFeatures">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle>Features</CardTitle>
                                <Badge>{{ release.changes.features.length }}</Badge>
                            </div>
                            <ChevronDown v-if="showFeatures" class="h-5 w-5" />
                            <ChevronRight v-else class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent v-if="showFeatures">
                        <ul class="space-y-2">
                            <li v-for="(change, idx) in release.changes.features" :key="idx" class="text-sm">
                                <code v-if="change.sha" class="text-xs text-muted-foreground mr-2">{{ change.sha.slice(0, 7) }}</code>
                                {{ change.description || change.message }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Fixes -->
                <Card v-if="release.changes?.fixes?.length > 0">
                    <CardHeader class="cursor-pointer pb-2" @click="showFixes = !showFixes">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle>Fixes</CardTitle>
                                <Badge variant="secondary">{{ release.changes.fixes.length }}</Badge>
                            </div>
                            <ChevronDown v-if="showFixes" class="h-5 w-5" />
                            <ChevronRight v-else class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent v-if="showFixes">
                        <ul class="space-y-2">
                            <li v-for="(change, idx) in release.changes.fixes" :key="idx" class="text-sm">
                                <code v-if="change.sha" class="text-xs text-muted-foreground mr-2">{{ change.sha.slice(0, 7) }}</code>
                                {{ change.description || change.message }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Release Notes -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Release Notes</CardTitle>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" @click="copyNotes">
                                <Copy class="h-4 w-4 mr-1" />
                                Copy
                            </Button>
                            <Button variant="outline" size="sm" @click="downloadNotes">
                                <Download class="h-4 w-4 mr-1" />
                                Download
                            </Button>
                            <Button v-if="isPending" variant="outline" size="sm" :disabled="regenerating" @click="regenerateNotes('technical')">
                                <Loader2 v-if="regenerating" class="h-4 w-4 mr-1 animate-spin" />
                                <RefreshCw v-else class="h-4 w-4 mr-1" />
                                Regenerate
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap font-mono text-sm bg-muted/50 p-4 rounded-lg">{{ release.release_notes }}</div>
                </CardContent>
            </Card>

            <!-- Commits -->
            <Card>
                <CardHeader class="cursor-pointer" @click="showCommits = !showCommits">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <GitCommit class="h-5 w-5" />
                            <CardTitle>Commits Analyzed</CardTitle>
                            <Badge variant="outline">{{ release.commits?.length || 0 }}</Badge>
                        </div>
                        <ChevronDown v-if="showCommits" class="h-5 w-5" />
                        <ChevronRight v-else class="h-5 w-5" />
                    </div>
                </CardHeader>
                <CardContent v-if="showCommits">
                    <div class="max-h-64 overflow-y-auto space-y-1">
                        <div v-for="commit in release.commits" :key="commit.sha" class="text-sm flex items-start gap-2 py-1">
                            <code class="text-xs text-muted-foreground">{{ commit.sha.slice(0, 7) }}</code>
                            <span class="truncate">{{ commit.message.split('\n')[0] }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Adjust Modal -->
            <div v-if="showAdjustModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Adjust Recommendation</CardTitle>
                        <CardDescription>Help us improve by telling us why you're adjusting</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitAdjustment" class="space-y-4">
                            <div class="space-y-2">
                                <Label>Final Version</Label>
                                <Input v-model="adjustForm.final_version" placeholder="v2.0.0" />
                            </div>

                            <div class="space-y-2">
                                <Label>Version Type</Label>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="adjustForm.final_type === 'MAJOR' ? 'destructive' : 'outline'"
                                        @click="adjustForm.final_type = 'MAJOR'"
                                    >MAJOR</Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="adjustForm.final_type === 'MINOR' ? 'default' : 'outline'"
                                        @click="adjustForm.final_type = 'MINOR'"
                                    >MINOR</Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="adjustForm.final_type === 'PATCH' ? 'secondary' : 'outline'"
                                        @click="adjustForm.final_type = 'PATCH'"
                                    >PATCH</Button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Reason (required)</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="reason in reasonCategories"
                                        :key="reason.value"
                                        :variant="adjustForm.reason_category === reason.value ? 'default' : 'outline'"
                                        class="cursor-pointer"
                                        @click="adjustForm.reason_category = reason.value"
                                    >
                                        {{ reason.label }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Additional Details (optional)</Label>
                                <textarea
                                    v-model="adjustForm.reason_details"
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Tell us more..."
                                />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="showAdjustModal = false">Cancel</Button>
                                <Button type="submit" :disabled="!adjustForm.reason_category || adjustForm.processing">
                                    Save Adjustment
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Reject Modal -->
            <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Reject Analysis</CardTitle>
                        <CardDescription>Why are you rejecting this analysis? (optional)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitRejection" class="space-y-4">
                            <div class="space-y-2">
                                <Label>Reason</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="reason in rejectReasons"
                                        :key="reason.value"
                                        :variant="rejectForm.reason_category === reason.value ? 'default' : 'outline'"
                                        class="cursor-pointer"
                                        @click="rejectForm.reason_category = reason.value"
                                    >
                                        {{ reason.label }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Additional Details (optional)</Label>
                                <textarea
                                    v-model="rejectForm.reason_details"
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Tell us more..."
                                />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="showRejectModal = false">Cancel</Button>
                                <Button type="submit" variant="destructive" :disabled="rejectForm.processing">
                                    Reject Analysis
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
            </div>
        </AppLayout>
    </template>
</template>

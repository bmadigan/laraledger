<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    GitBranch,
    Tag,
    Play,
    Eye,
    Loader2,
    CheckCircle,
    Clock,
    Users,
    FileCode,
    AlertCircle,
} from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';

interface Tag {
    name: string;
    commit: { sha: string };
}

interface Branch {
    name: string;
    protected: boolean;
}

interface RecentRelease {
    id: number;
    from_ref: string;
    to_ref: string;
    recommended_version: string;
    recommended_type: string;
    created_at: string;
}

interface Repository {
    id: number;
    name: string;
    full_name: string;
    default_branch: string;
}

interface PreviewCommit {
    sha: string;
    message: string;
    author: string;
    date: string;
}

const props = defineProps<{
    repository: Repository;
    tags: Tag[];
    branches: Branch[];
    recentReleases: RecentRelease[];
    defaultBranch: string;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        { title: 'Repositories', href: '/repositories' },
    ];

    if (props.repository?.id) {
        items.push({
            title: props.repository.name ?? 'Repository',
            href: `/repositories/${props.repository.id}`,
        });
        items.push({
            title: 'New Analysis',
            href: `/repositories/${props.repository.id}/analyze`,
        });
    }

    return items;
});

// Default to branch if no tags exist
const hasTags = computed(() => (props.tags?.length ?? 0) > 0);
const defaultFromRef = computed(() => hasTags.value ? props.tags?.[0]?.name ?? '' : (props.branches?.[0]?.name ?? ''));

const form = useForm({
    from_ref: defaultFromRef.value,
    to_ref: props.defaultBranch ?? '',
    force_ai: false,
    depth: 'standard',
    note_style: 'technical',
});

const previewing = ref(false);
const preview = ref<{
    commit_count: number;
    files_changed: number;
    contributors: string[];
    commits: PreviewCommit[];
} | null>(null);
const previewError = ref<string | null>(null);

const fromRefType = ref<'tag' | 'branch'>(hasTags.value ? 'tag' : 'branch');
const toRefType = ref<'tag' | 'branch'>('branch');

const fromOptions = computed(() => {
    if (fromRefType.value === 'tag') {
        return props.tags.map(t => ({ value: t.name, label: t.name }));
    }
    return props.branches.map(b => ({ value: b.name, label: b.name + (b.protected ? ' (Protected)' : '') }));
});

const toOptions = computed(() => {
    if (toRefType.value === 'tag') {
        return props.tags.map(t => ({ value: t.name, label: t.name }));
    }
    return props.branches.map(b => ({ value: b.name, label: b.name + (b.protected ? ' (Protected)' : '') }));
});

const canAnalyze = computed(() => {
    return form.from_ref && form.to_ref && form.from_ref !== form.to_ref;
});

const previewChanges = async () => {
    if (!canAnalyze.value) return;

    previewing.value = true;
    preview.value = null;
    previewError.value = null;

    try {
        const response = await fetch(AnalysisController.preview(props.repository.id).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                from_ref: form.from_ref,
                to_ref: form.to_ref,
            }),
        });

        const data = await response.json();

        if (data.success) {
            preview.value = data;
        } else {
            previewError.value = data.error || 'Failed to preview changes';
        }
    } catch (error) {
        previewError.value = 'Failed to fetch preview. Please try again.';
    } finally {
        previewing.value = false;
    }
};

const startAnalysis = () => {
    form.post(AnalysisController.store(props.repository.id).url);
};

const getTypeVariant = (type: string): 'destructive' | 'default' | 'secondary' | 'outline' => {
    switch (type) {
        case 'MAJOR': return 'destructive';
        case 'MINOR': return 'default';
        case 'PATCH': return 'secondary';
        default: return 'outline';
    }
};
</script>

<template>
    <div v-if="!repository?.id" class="flex items-center justify-center h-screen">
        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
    <template v-else>
        <Head :title="`New Analysis - ${repository.name}`" />

        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex h-full flex-1 gap-6 p-4">
            <!-- Main Form -->
            <div class="flex-1 max-w-3xl space-y-6">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">New Release Analysis</h1>
                    <p class="text-muted-foreground">
                        Analyze changes between two references in {{ repository.full_name }}
                    </p>
                </div>

                <form @submit.prevent="startAnalysis" class="space-y-6">
                    <!-- No Tags Notice -->
                    <div v-if="!hasTags" class="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950">
                        <AlertCircle class="h-5 w-5 text-yellow-600 dark:text-yellow-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-medium text-yellow-800 dark:text-yellow-200">No tags found</p>
                            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                This repository has no tags. Select two branches to compare instead.
                                For accurate version analysis, consider creating git tags for your releases.
                            </p>
                        </div>
                    </div>

                    <!-- Reference Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Select References</CardTitle>
                            <CardDescription>
                                Choose the starting and ending points for the analysis
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- From Reference -->
                            <div class="space-y-3">
                                <Label>From (Base Reference)</Label>
                                <div class="flex gap-2 mb-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="fromRefType === 'tag' ? 'default' : 'outline'"
                                        @click="fromRefType = 'tag'; form.from_ref = tags[0]?.name ?? ''"
                                    >
                                        <Tag class="h-4 w-4 mr-1" />
                                        Tags
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="fromRefType === 'branch' ? 'default' : 'outline'"
                                        @click="fromRefType = 'branch'; form.from_ref = branches[0]?.name ?? ''"
                                    >
                                        <GitBranch class="h-4 w-4 mr-1" />
                                        Branches
                                    </Button>
                                </div>
                                <select
                                    v-model="form.from_ref"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option v-for="opt in fromOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Starting point for comparison (usually your last release tag)
                                </p>
                            </div>

                            <!-- To Reference -->
                            <div class="space-y-3">
                                <Label>To (Head Reference)</Label>
                                <div class="flex gap-2 mb-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="toRefType === 'tag' ? 'default' : 'outline'"
                                        @click="toRefType = 'tag'; form.to_ref = tags[0]?.name ?? ''"
                                    >
                                        <Tag class="h-4 w-4 mr-1" />
                                        Tags
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="toRefType === 'branch' ? 'default' : 'outline'"
                                        @click="toRefType = 'branch'; form.to_ref = defaultBranch"
                                    >
                                        <GitBranch class="h-4 w-4 mr-1" />
                                        Branches
                                    </Button>
                                </div>
                                <select
                                    v-model="form.to_ref"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option v-for="opt in toOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Ending point (usually your current branch)
                                </p>
                            </div>

                            <!-- Preview Button -->
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!canAnalyze || previewing"
                                @click="previewChanges"
                            >
                                <Loader2 v-if="previewing" class="mr-2 h-4 w-4 animate-spin" />
                                <Eye v-else class="mr-2 h-4 w-4" />
                                Preview Changes
                            </Button>

                            <!-- Preview Results -->
                            <div v-if="preview" class="p-4 rounded-lg bg-muted/50 space-y-3">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <div class="flex items-center justify-center gap-1 text-muted-foreground text-sm">
                                            <FileCode class="h-4 w-4" />
                                            Commits
                                        </div>
                                        <p class="text-2xl font-bold">{{ preview.commit_count }}</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-center gap-1 text-muted-foreground text-sm">
                                            <FileCode class="h-4 w-4" />
                                            Files
                                        </div>
                                        <p class="text-2xl font-bold">{{ preview.files_changed }}</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-center gap-1 text-muted-foreground text-sm">
                                            <Users class="h-4 w-4" />
                                            Contributors
                                        </div>
                                        <p class="text-2xl font-bold">{{ preview.contributors.length }}</p>
                                    </div>
                                </div>

                                <div class="max-h-40 overflow-y-auto space-y-1">
                                    <div
                                        v-for="commit in preview.commits"
                                        :key="commit.sha"
                                        class="text-sm flex items-start gap-2"
                                    >
                                        <code class="text-xs text-muted-foreground">{{ commit.sha }}</code>
                                        <span class="truncate">{{ commit.message }}</span>
                                    </div>
                                    <p v-if="preview.commit_count > 20" class="text-xs text-muted-foreground">
                                        ...and {{ preview.commit_count - 20 }} more commits
                                    </p>
                                </div>
                            </div>

                            <div v-if="previewError" class="p-4 rounded-lg bg-destructive/10 border border-destructive/20">
                                <div class="flex items-center gap-2 text-destructive">
                                    <AlertCircle class="h-4 w-4" />
                                    <span>{{ previewError }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Analysis Options -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Analysis Options</CardTitle>
                            <CardDescription>
                                Configure how the analysis should be performed
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input
                                    id="force_ai"
                                    type="checkbox"
                                    v-model="form.force_ai"
                                    class="h-4 w-4 rounded border-input"
                                />
                                <Label for="force_ai" class="font-normal">
                                    Force AI Analysis (skip heuristic threshold)
                                </Label>
                            </div>

                            <div class="space-y-2">
                                <Label>Analysis Depth</Label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" v-model="form.depth" value="standard" />
                                        <span class="text-sm">Standard</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" v-model="form.depth" value="deep" />
                                        <span class="text-sm">Deep (include diff analysis)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Release Note Style</Label>
                                <select
                                    v-model="form.note_style"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="technical">Technical</option>
                                    <option value="user_friendly">User-Friendly</option>
                                    <option value="marketing">Marketing</option>
                                </select>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <div class="flex justify-between">
                        <Link :href="RepositoryController.show(repository.id).url">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            type="submit"
                            :disabled="!canAnalyze || form.processing"
                        >
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            <Play v-else class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Analyzing...' : 'Start Analysis' }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Sidebar - Recent Analyses -->
            <div class="w-80 hidden lg:block">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Recent Analyses</CardTitle>
                        <CardDescription>Quick access to recent work</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentReleases.length > 0" class="space-y-3">
                            <Link
                                v-for="release in recentReleases.filter(r => r?.id)"
                                :key="release.id"
                                :href="AnalysisController.show(repository.id, release.id).url"
                                class="block p-3 rounded-lg hover:bg-muted/50 transition-colors"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-mono text-sm">{{ release.recommended_version }}</span>
                                    <Badge :variant="getTypeVariant(release.recommended_type)" class="text-xs">
                                        {{ release.recommended_type }}
                                    </Badge>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ release.from_ref }} → {{ release.to_ref }}
                                </div>
                                <div class="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                                    <Clock class="h-3 w-3" />
                                    {{ formatDistanceToNow(new Date(release.created_at), { addSuffix: true }) }}
                                </div>
                            </Link>
                        </div>
                        <div v-else class="text-center py-6">
                            <p class="text-sm text-muted-foreground">No recent analyses</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
        </AppLayout>
    </template>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import ReleaseController from '@/actions/App/Http/Controllers/ReleaseController';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Search,
    Filter,
    Download,
    CheckCircle,
    XCircle,
    AlertCircle,
    Clock,
    ChevronLeft,
    ChevronRight,
    X,
} from 'lucide-vue-next';
import { format } from 'date-fns';

interface Repository {
    id: number;
    name: string;
    releases_count: number;
}

interface Release {
    id: number;
    repository_id: number;
    repository: { id: number; name: string; full_name: string };
    recommended_version: string;
    recommended_type: string;
    final_version: string | null;
    final_type: string | null;
    confidence: number;
    status: string;
    created_at: string;
}

interface PaginatedReleases {
    data: Release[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
    search: string | null;
    repository_id: number | null;
    types: string | null;
    statuses: string | null;
    from_date: string | null;
    to_date: string | null;
    min_confidence: number | null;
    max_confidence: number | null;
    sort: string;
    direction: string;
}

interface Stats {
    total: number;
    accepted: number;
    adjusted: number;
    rejected: number;
    avg_confidence: number;
}

const props = defineProps<{
    releases: PaginatedReleases;
    repositories: Repository[];
    stats: Stats;
    filters: Filters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Releases', href: ReleaseController.index().url },
];

const search = ref(props.filters.search ?? '');
const selectedRepo = ref<number | null>(props.filters.repository_id);
const selectedTypes = ref<string[]>(props.filters.types?.split(',') ?? []);
const selectedStatuses = ref<string[]>(props.filters.statuses?.split(',') ?? []);
const showFilters = ref(false);

const applyFilters = () => {
    router.get(ReleaseController.index().url, {
        search: search.value || undefined,
        repository_id: selectedRepo.value || undefined,
        types: selectedTypes.value.length ? selectedTypes.value.join(',') : undefined,
        statuses: selectedStatuses.value.length ? selectedStatuses.value.join(',') : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const debouncedSearch = useDebounceFn(applyFilters, 300);

watch(search, () => {
    debouncedSearch();
});

const clearFilters = () => {
    search.value = '';
    selectedRepo.value = null;
    selectedTypes.value = [];
    selectedStatuses.value = [];
    router.get(ReleaseController.index().url);
};

const hasActiveFilters = () => {
    return search.value || selectedRepo.value || selectedTypes.value.length || selectedStatuses.value.length;
};

const toggleType = (type: string) => {
    if (selectedTypes.value.includes(type)) {
        selectedTypes.value = selectedTypes.value.filter(t => t !== type);
    } else {
        selectedTypes.value = [...selectedTypes.value, type];
    }
    applyFilters();
};

const toggleStatus = (status: string) => {
    if (selectedStatuses.value.includes(status)) {
        selectedStatuses.value = selectedStatuses.value.filter(s => s !== status);
    } else {
        selectedStatuses.value = [...selectedStatuses.value, status];
    }
    applyFilters();
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'accepted': return CheckCircle;
        case 'adjusted': return AlertCircle;
        case 'rejected': return XCircle;
        default: return Clock;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'accepted': return 'text-green-500';
        case 'adjusted': return 'text-yellow-500';
        case 'rejected': return 'text-red-500';
        default: return 'text-muted-foreground';
    }
};

const getTypeVariant = (type: string): 'destructive' | 'default' | 'secondary' | 'outline' => {
    switch (type) {
        case 'MAJOR': return 'destructive';
        case 'MINOR': return 'default';
        case 'PATCH': return 'secondary';
        default: return 'outline';
    }
};

const exportData = (format: 'csv' | 'json') => {
    const params = new URLSearchParams();
    params.set('format', format);
    if (selectedRepo.value) params.set('repository_id', String(selectedRepo.value));
    if (selectedTypes.value.length) params.set('types', selectedTypes.value.join(','));
    if (selectedStatuses.value.length) params.set('statuses', selectedStatuses.value.join(','));

    window.location.href = `${ReleaseController.export().url}?${params.toString()}`;
};
</script>

<template>
    <Head title="Release History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Release History</h1>
                    <p class="text-muted-foreground">All releases across your repositories</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="exportData('csv')">
                        <Download class="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                    <Button variant="outline" @click="exportData('json')">
                        <Download class="mr-2 h-4 w-4" />
                        Export JSON
                    </Button>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="flex items-center gap-4 text-sm">
                <span class="text-muted-foreground">
                    Showing {{ releases.data.length }} of {{ stats.total }} releases
                </span>
                <span class="text-muted-foreground">|</span>
                <span class="flex items-center gap-1">
                    <CheckCircle class="h-4 w-4 text-green-500" />
                    {{ stats.accepted }} accepted
                </span>
                <span class="flex items-center gap-1">
                    <AlertCircle class="h-4 w-4 text-yellow-500" />
                    {{ stats.adjusted }} adjusted
                </span>
                <span class="flex items-center gap-1">
                    <XCircle class="h-4 w-4 text-red-500" />
                    {{ stats.rejected }} rejected
                </span>
                <span class="text-muted-foreground">|</span>
                <span>Avg confidence: {{ stats.avg_confidence }}%</span>
            </div>

            <!-- Search and Filters -->
            <div class="flex items-center gap-4">
                <div class="relative flex-1 max-w-md">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Search by repository or version..."
                        class="pl-10"
                    />
                </div>

                <Button variant="outline" @click="showFilters = !showFilters">
                    <Filter class="mr-2 h-4 w-4" />
                    Filters
                    <Badge v-if="hasActiveFilters()" variant="secondary" class="ml-2">
                        {{ (selectedTypes.length || 0) + (selectedStatuses.length || 0) + (selectedRepo ? 1 : 0) }}
                    </Badge>
                </Button>

                <Button v-if="hasActiveFilters()" variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-1 h-4 w-4" />
                    Clear
                </Button>
            </div>

            <!-- Filter Panel -->
            <Card v-if="showFilters">
                <CardContent class="pt-6">
                    <div class="grid gap-6 md:grid-cols-3">
                        <!-- Repository Filter -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Repository</label>
                            <select
                                v-model="selectedRepo"
                                @change="applyFilters"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            >
                                <option :value="null">All Repositories</option>
                                <option v-for="repo in repositories" :key="repo.id" :value="repo.id">
                                    {{ repo.name }} ({{ repo.releases_count }})
                                </option>
                            </select>
                        </div>

                        <!-- Version Type Filter -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Version Type</label>
                            <div class="flex gap-2">
                                <Badge
                                    :variant="selectedTypes.includes('MAJOR') ? 'destructive' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleType('MAJOR')"
                                >
                                    MAJOR
                                </Badge>
                                <Badge
                                    :variant="selectedTypes.includes('MINOR') ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleType('MINOR')"
                                >
                                    MINOR
                                </Badge>
                                <Badge
                                    :variant="selectedTypes.includes('PATCH') ? 'secondary' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleType('PATCH')"
                                >
                                    PATCH
                                </Badge>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Status</label>
                            <div class="flex gap-2">
                                <Badge
                                    :variant="selectedStatuses.includes('accepted') ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleStatus('accepted')"
                                >
                                    Accepted
                                </Badge>
                                <Badge
                                    :variant="selectedStatuses.includes('adjusted') ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleStatus('adjusted')"
                                >
                                    Adjusted
                                </Badge>
                                <Badge
                                    :variant="selectedStatuses.includes('rejected') ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleStatus('rejected')"
                                >
                                    Rejected
                                </Badge>
                                <Badge
                                    :variant="selectedStatuses.includes('pending') ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleStatus('pending')"
                                >
                                    Pending
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Release Table -->
            <Card>
                <CardContent class="p-0">
                    <div v-if="releases.data.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Repository</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Version</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Recommended</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Final</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Confidence</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="release in releases.data"
                                    :key="release.id"
                                    class="border-b last:border-0 hover:bg-muted/50 cursor-pointer"
                                    @click="router.visit(AnalysisController.show(release.repository_id, release.id).url)"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="RepositoryController.show(release.repository_id).url"
                                            class="text-primary hover:underline"
                                            @click.stop
                                        >
                                            {{ release.repository?.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-sm">
                                        {{ release.recommended_version }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="getTypeVariant(release.recommended_type)">
                                            {{ release.recommended_type }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <template v-if="release.final_version">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-sm">{{ release.final_version }}</span>
                                                <Badge v-if="release.final_type" :variant="getTypeVariant(release.final_type)">
                                                    {{ release.final_type }}
                                                </Badge>
                                            </div>
                                        </template>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-16 rounded-full bg-muted overflow-hidden">
                                                <div
                                                    class="h-full bg-primary"
                                                    :style="{ width: `${release.confidence}%` }"
                                                />
                                            </div>
                                            <span class="text-sm">{{ release.confidence }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="getStatusIcon(release.status)"
                                                :class="['h-4 w-4', getStatusColor(release.status)]"
                                            />
                                            <span class="capitalize text-sm">{{ release.status }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-muted-foreground">
                                        {{ format(new Date(release.created_at), 'MMM d, yyyy') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <Clock class="h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-lg font-semibold">No releases found</h3>
                        <p v-if="hasActiveFilters()" class="text-muted-foreground mb-4">
                            Try adjusting your filters to see more results.
                        </p>
                        <p v-else class="text-muted-foreground mb-4">
                            Start analyzing your repositories to see releases here.
                        </p>
                        <Button v-if="hasActiveFilters()" variant="outline" @click="clearFilters">
                            Clear Filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="releases.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Page {{ releases.current_page }} of {{ releases.last_page }}
                </p>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="releases.current_page === 1"
                        @click="router.get(releases.links.find(l => l.label.includes('Previous'))?.url || '')"
                    >
                        <ChevronLeft class="h-4 w-4 mr-1" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="releases.current_page === releases.last_page"
                        @click="router.get(releases.links.find(l => l.label.includes('Next'))?.url || '')"
                    >
                        Next
                        <ChevronRight class="h-4 w-4 ml-1" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AnalyticsController from '@/actions/App/Http/Controllers/AnalyticsController';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    XCircle,
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
} from 'lucide-vue-next';
import { format } from 'date-fns';

interface Repository {
    id: number;
    name: string;
    releases_count: number;
}

interface Feedback {
    id: number;
    reason_category: string;
    reason_details: string | null;
}

interface Release {
    id: number;
    repository_id: number;
    repository: { id: number; name: string };
    recommended_version: string;
    recommended_type: string;
    final_version: string | null;
    final_type: string | null;
    confidence: number;
    status: string;
    created_at: string;
    feedback: Feedback | null;
}

interface PaginatedPredictions {
    data: Release[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface ReasonCategory {
    reason_category: string;
    count: number;
}

interface Filters {
    repository_id: number | null;
    from_date: string | null;
    to_date: string | null;
}

const props = defineProps<{
    predictions: PaginatedPredictions;
    reasonCategories: ReasonCategory[];
    repositories: Repository[];
    filters: Filters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: AnalyticsController.index().url },
    { title: 'Missed Predictions', href: AnalyticsController.missedPredictions().url },
];

const updateFilters = (key: string, value: string | number | null) => {
    router.get(AnalyticsController.missedPredictions().url, {
        ...props.filters,
        [key]: value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getReasonLabel = (category: string | null): string => {
    if (!category) return 'No reason provided';

    const labels: Record<string, string> = {
        'breaking_missed': 'Breaking change missed',
        'not_breaking': 'Not actually breaking',
        'feature_miscategorized': 'Feature miscategorized',
        'fix_miscategorized': 'Fix miscategorized',
        'version_wrong': 'Wrong version number',
        'wrong_refs': 'Wrong refs selected',
        'inaccurate': 'Analysis is inaccurate',
        'changed_mind': 'Changed my mind',
        'other': 'Other',
    };

    return labels[category] || category;
};

const getTypeVariant = (type: string): 'destructive' | 'default' | 'secondary' | 'outline' => {
    switch (type) {
        case 'MAJOR': return 'destructive';
        case 'MINOR': return 'default';
        case 'PATCH': return 'secondary';
        default: return 'outline';
    }
};

const exportPredictions = () => {
    const params = new URLSearchParams();
    params.set('format', 'json');
    if (props.filters.repository_id) params.set('repository_id', String(props.filters.repository_id));

    // Create a simple JSON export
    const data = props.predictions.data.map(p => ({
        date: p.created_at,
        repository: p.repository?.name,
        predicted_version: p.recommended_version,
        predicted_type: p.recommended_type,
        actual_version: p.final_version,
        actual_type: p.final_type,
        confidence: p.confidence,
        status: p.status,
        reason_category: p.feedback?.reason_category,
        reason_details: p.feedback?.reason_details,
    }));

    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `missed-predictions-${format(new Date(), 'yyyy-MM-dd')}.json`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Missed Predictions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Missed Predictions</h1>
                    <p class="text-muted-foreground">Review cases where recommendations were adjusted or rejected</p>
                </div>
                <Button variant="outline" @click="exportPredictions">
                    <Download class="mr-2 h-4 w-4" />
                    Export JSON
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex gap-4">
                <select
                    :value="filters.repository_id || ''"
                    @change="updateFilters('repository_id', ($event.target as HTMLSelectElement).value || null)"
                    class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                >
                    <option value="">All Repositories</option>
                    <option v-for="repo in repositories" :key="repo.id" :value="repo.id">
                        {{ repo.name }}
                    </option>
                </select>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Reason Categories Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Common Reasons</CardTitle>
                        <CardDescription>Why users adjusted predictions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="reasonCategories.length > 0" class="space-y-3">
                            <div v-for="reason in reasonCategories" :key="reason.reason_category" class="flex items-center justify-between">
                                <span class="text-sm">{{ getReasonLabel(reason.reason_category) }}</span>
                                <Badge variant="secondary">{{ reason.count }}</Badge>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-muted-foreground">
                            <p>No feedback data yet</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Predictions Table -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardContent class="p-0">
                            <div v-if="predictions.data.length > 0" class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b bg-muted/50">
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Date</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Repository</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Predicted</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Actual</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Confidence</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Reason</th>
                                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="prediction in predictions.data"
                                            :key="prediction.id"
                                            class="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                                {{ format(new Date(prediction.created_at), 'MMM d, yyyy') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <Link
                                                    :href="RepositoryController.show(prediction.repository_id).url"
                                                    class="text-primary hover:underline"
                                                >
                                                    {{ prediction.repository?.name }}
                                                </Link>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-sm">{{ prediction.recommended_version }}</span>
                                                    <Badge :variant="getTypeVariant(prediction.recommended_type)">
                                                        {{ prediction.recommended_type }}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <template v-if="prediction.status === 'adjusted' && prediction.final_version">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-mono text-sm">{{ prediction.final_version }}</span>
                                                        <Badge v-if="prediction.final_type" :variant="getTypeVariant(prediction.final_type)">
                                                            {{ prediction.final_type }}
                                                        </Badge>
                                                    </div>
                                                </template>
                                                <Badge v-else variant="destructive">Rejected</Badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 w-12 rounded-full bg-muted overflow-hidden">
                                                        <div
                                                            class="h-full"
                                                            :class="prediction.confidence >= 85 ? 'bg-red-500' : 'bg-yellow-500'"
                                                            :style="{ width: `${prediction.confidence}%` }"
                                                        />
                                                    </div>
                                                    <span class="text-sm" :class="prediction.confidence >= 85 ? 'text-red-600 font-medium' : ''">
                                                        {{ prediction.confidence }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <span v-if="prediction.feedback?.reason_category">
                                                    {{ getReasonLabel(prediction.feedback.reason_category) }}
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <Link
                                                    :href="AnalysisController.show(prediction.repository_id, prediction.id).url"
                                                    class="text-primary hover:underline"
                                                >
                                                    <ExternalLink class="h-4 w-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                                <AlertCircle class="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 class="text-lg font-semibold">No missed predictions</h3>
                                <p class="text-muted-foreground">
                                    All recommendations have been accepted. Great work!
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Pagination -->
                    <div v-if="predictions.last_page > 1" class="flex items-center justify-between mt-4">
                        <p class="text-sm text-muted-foreground">
                            Page {{ predictions.current_page }} of {{ predictions.last_page }}
                        </p>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="predictions.current_page === 1"
                                @click="router.get(predictions.links.find(l => l.label.includes('Previous'))?.url || '')"
                            >
                                <ChevronLeft class="h-4 w-4 mr-1" />
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="predictions.current_page === predictions.last_page"
                                @click="router.get(predictions.links.find(l => l.label.includes('Next'))?.url || '')"
                            >
                                Next
                                <ChevronRight class="h-4 w-4 ml-1" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High Confidence Misses Warning -->
            <Card v-if="predictions.data.some(p => p.confidence >= 85)" class="border-yellow-500/50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-yellow-600">
                        <AlertCircle class="h-5 w-5" />
                        High Confidence Misses Detected
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground mb-2">
                        Some predictions with 85%+ confidence were wrong. This indicates the system may be overconfident
                        in certain scenarios. Review these cases to identify patterns for improvement.
                    </p>
                    <ul class="text-sm space-y-1">
                        <li v-for="prediction in predictions.data.filter(p => p.confidence >= 85).slice(0, 3)" :key="prediction.id">
                            <Link :href="AnalysisController.show(prediction.repository_id, prediction.id).url" class="text-primary hover:underline">
                                {{ prediction.repository?.name }} - {{ prediction.recommended_version }} ({{ prediction.confidence }}% confidence)
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

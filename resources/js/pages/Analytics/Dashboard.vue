<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AnalyticsController from '@/actions/App/Http/Controllers/AnalyticsController';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle,
    AlertTriangle,
    TrendingUp,
    DollarSign,
    Clock,
    Cpu,
    Bot,
    Target,
    AlertCircle,
} from 'lucide-vue-next';

interface Repository {
    id: number;
    name: string;
    releases_count: number;
}

interface Metrics {
    totalReleases: number;
    acceptanceRate: number;
    breakingDetectionRate: number;
    avgConfidence: number;
    totalCost: number;
    avgCostPerRelease: number;
    accepted: number;
    adjusted: number;
    rejected: number;
}

interface AcceptanceDataPoint {
    date: string;
    rate: number;
    count: number;
}

interface CalibrationDataPoint {
    bucket: string;
    expected: number;
    actual: number;
    count: number;
}

interface VersionDistribution {
    type: string;
    count: number;
    percentage: number;
}

interface AnalysisSourceUsage {
    heuristic: number;
    ai: number;
    combined: number;
}

interface CostDataPoint {
    date: string;
    cost: number;
    count: number;
}

interface Charts {
    acceptanceOverTime: AcceptanceDataPoint[];
    confidenceCalibration: CalibrationDataPoint[];
    versionDistribution: VersionDistribution[];
    analysisSourceUsage: AnalysisSourceUsage;
    costOverTime: CostDataPoint[];
}

interface LatencyMetrics {
    avg: number;
    p95: number;
    min: number;
    max: number;
}

interface Filters {
    range: string;
    repository_id: number | null;
}

const props = defineProps<{
    metrics: Metrics;
    charts: Charts;
    latencyMetrics: LatencyMetrics;
    repositories: Repository[];
    filters: Filters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: AnalyticsController.index().url },
];

const dateRanges = [
    { value: '7', label: 'Last 7 days' },
    { value: '30', label: 'Last 30 days' },
    { value: '90', label: 'Last 90 days' },
    { value: '365', label: 'Last 12 months' },
    { value: 'all', label: 'All time' },
];

const updateFilters = (key: string, value: string | number | null) => {
    router.get(AnalyticsController.index().url, {
        ...props.filters,
        [key]: value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const totalAnalysisSource = computed(() => {
    return props.charts.analysisSourceUsage.heuristic +
        props.charts.analysisSourceUsage.ai +
        props.charts.analysisSourceUsage.combined;
});

const heuristicPercentage = computed(() => {
    if (totalAnalysisSource.value === 0) return 0;
    return Math.round((props.charts.analysisSourceUsage.heuristic / totalAnalysisSource.value) * 100);
});

const getTypeColor = (type: string) => {
    switch (type) {
        case 'MAJOR': return 'bg-red-500';
        case 'MINOR': return 'bg-blue-500';
        case 'PATCH': return 'bg-green-500';
        default: return 'bg-gray-500';
    }
};

const getCalibrationColor = (expected: number, actual: number) => {
    const diff = actual - expected;
    if (diff >= -5) return 'bg-green-500';
    if (diff >= -15) return 'bg-yellow-500';
    return 'bg-red-500';
};
</script>

<template>
    <Head title="Accuracy Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header with Filters -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Accuracy Dashboard</h1>
                    <p class="text-muted-foreground">Track recommendation accuracy and system performance</p>
                </div>
                <div class="flex gap-4">
                    <select
                        :value="filters.range"
                        @change="updateFilters('range', ($event.target as HTMLSelectElement).value)"
                        class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option v-for="range in dateRanges" :key="range.value" :value="range.value">
                            {{ range.label }}
                        </option>
                    </select>
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
            </div>

            <!-- Key Metrics Row -->
            <div class="grid gap-4 md:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-2">
                            <TrendingUp class="h-4 w-4" />
                            Total Releases
                        </CardDescription>
                        <CardTitle class="text-3xl">{{ metrics.totalReleases }}</CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4" />
                            Acceptance Rate
                        </CardDescription>
                        <CardTitle class="text-3xl" :class="metrics.acceptanceRate >= 85 ? 'text-green-600' : metrics.acceptanceRate >= 70 ? 'text-yellow-600' : 'text-red-600'">
                            {{ metrics.acceptanceRate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full bg-green-500" :style="{ width: `${metrics.acceptanceRate}%` }" />
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Target: 85%</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4" />
                            Breaking Detection
                        </CardDescription>
                        <CardTitle class="text-3xl" :class="metrics.breakingDetectionRate >= 95 ? 'text-green-600' : metrics.breakingDetectionRate >= 90 ? 'text-yellow-600' : 'text-red-600'">
                            {{ metrics.breakingDetectionRate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full bg-red-500" :style="{ width: `${metrics.breakingDetectionRate}%` }" />
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Target: 95%</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-2">
                            <Target class="h-4 w-4" />
                            Avg Confidence
                        </CardDescription>
                        <CardTitle class="text-3xl">{{ metrics.avgConfidence }}%</CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-2">
                            <DollarSign class="h-4 w-4" />
                            Total Cost
                        </CardDescription>
                        <CardTitle class="text-3xl">${{ metrics.totalCost.toFixed(2) }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-muted-foreground">
                            ${{ metrics.avgCostPerRelease.toFixed(4) }}/release
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts Row -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Confidence Calibration -->
                <Card>
                    <CardHeader>
                        <CardTitle>Confidence Calibration</CardTitle>
                        <CardDescription>Are confidence scores accurate?</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="charts.confidenceCalibration.some(c => c.count > 0)" class="space-y-4">
                            <div v-for="bucket in charts.confidenceCalibration" :key="bucket.bucket" class="space-y-1">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ bucket.bucket }}</span>
                                    <span class="text-muted-foreground">{{ bucket.count }} releases</span>
                                </div>
                                <div class="flex gap-2 items-center">
                                    <div class="flex-1 h-4 rounded-full bg-muted overflow-hidden relative">
                                        <!-- Expected line -->
                                        <div
                                            class="absolute top-0 bottom-0 w-0.5 bg-gray-400 z-10"
                                            :style="{ left: `${bucket.expected}%` }"
                                        />
                                        <!-- Actual bar -->
                                        <div
                                            :class="['h-full', getCalibrationColor(bucket.expected, bucket.actual)]"
                                            :style="{ width: `${bucket.actual}%` }"
                                        />
                                    </div>
                                    <span class="text-sm w-12 text-right">{{ bucket.actual }}%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-muted-foreground pt-2">
                                <span class="flex items-center gap-1">
                                    <div class="w-2 h-2 bg-gray-400" /> Expected
                                </span>
                                <span class="flex items-center gap-1">
                                    <div class="w-2 h-2 bg-green-500" /> On target
                                </span>
                                <span class="flex items-center gap-1">
                                    <div class="w-2 h-2 bg-red-500" /> Overconfident
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <AlertCircle class="h-8 w-8 mx-auto mb-2" />
                            <p>Insufficient data for calibration analysis</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Version Type Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle>Version Type Distribution</CardTitle>
                        <CardDescription>What kinds of releases?</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="metrics.totalReleases > 0" class="space-y-4">
                            <div v-for="dist in charts.versionDistribution" :key="dist.type" class="space-y-1">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <Badge :variant="dist.type === 'MAJOR' ? 'destructive' : dist.type === 'MINOR' ? 'default' : 'secondary'">
                                            {{ dist.type }}
                                        </Badge>
                                    </span>
                                    <span>{{ dist.count }} ({{ dist.percentage }}%)</span>
                                </div>
                                <div class="h-3 rounded-full bg-muted overflow-hidden">
                                    <div
                                        :class="['h-full', getTypeColor(dist.type)]"
                                        :style="{ width: `${dist.percentage}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p>No release data available</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Second Charts Row -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- AI vs Heuristic Usage -->
                <Card>
                    <CardHeader>
                        <CardTitle>Analysis Source Usage</CardTitle>
                        <CardDescription>How often is AI needed?</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="totalAnalysisSource > 0" class="space-y-4">
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <div class="h-8 rounded-full bg-muted overflow-hidden flex">
                                        <div
                                            class="h-full bg-blue-500 flex items-center justify-center text-xs text-white font-medium"
                                            :style="{ width: `${heuristicPercentage}%` }"
                                        >
                                            <span v-if="heuristicPercentage >= 20">{{ heuristicPercentage }}%</span>
                                        </div>
                                        <div
                                            class="h-full bg-purple-500 flex items-center justify-center text-xs text-white font-medium"
                                            :style="{ width: `${100 - heuristicPercentage}%` }"
                                        >
                                            <span v-if="100 - heuristicPercentage >= 20">{{ 100 - heuristicPercentage }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <Cpu class="h-5 w-5 mx-auto text-blue-500" />
                                    <p class="text-lg font-bold">{{ charts.analysisSourceUsage.heuristic }}</p>
                                    <p class="text-xs text-muted-foreground">Heuristic Only</p>
                                </div>
                                <div>
                                    <Bot class="h-5 w-5 mx-auto text-purple-500" />
                                    <p class="text-lg font-bold">{{ charts.analysisSourceUsage.ai }}</p>
                                    <p class="text-xs text-muted-foreground">AI Only</p>
                                </div>
                                <div>
                                    <div class="h-5 w-5 mx-auto flex items-center justify-center">
                                        <Cpu class="h-3 w-3 text-blue-500" />
                                        <Bot class="h-3 w-3 text-purple-500" />
                                    </div>
                                    <p class="text-lg font-bold">{{ charts.analysisSourceUsage.combined }}</p>
                                    <p class="text-xs text-muted-foreground">Combined</p>
                                </div>
                            </div>
                            <p v-if="heuristicPercentage >= 70" class="text-xs text-green-600">
                                Good! Higher heuristic usage means lower costs.
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Target: 70%+ heuristic-only for cost optimization
                            </p>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p>No analysis data available</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Latency Metrics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5" />
                            Performance Metrics
                        </CardTitle>
                        <CardDescription>Analysis latency statistics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-lg bg-muted/50 text-center">
                                <p class="text-2xl font-bold">{{ latencyMetrics.avg }}s</p>
                                <p class="text-xs text-muted-foreground">Average</p>
                            </div>
                            <div class="p-4 rounded-lg bg-muted/50 text-center">
                                <p class="text-2xl font-bold" :class="latencyMetrics.p95 <= 10 ? 'text-green-600' : 'text-yellow-600'">
                                    {{ latencyMetrics.p95 }}s
                                </p>
                                <p class="text-xs text-muted-foreground">P95 (Target: &lt;10s)</p>
                            </div>
                            <div class="p-4 rounded-lg bg-muted/50 text-center">
                                <p class="text-2xl font-bold">{{ latencyMetrics.min }}s</p>
                                <p class="text-xs text-muted-foreground">Minimum</p>
                            </div>
                            <div class="p-4 rounded-lg bg-muted/50 text-center">
                                <p class="text-2xl font-bold">{{ latencyMetrics.max }}s</p>
                                <p class="text-xs text-muted-foreground">Maximum</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Decision Summary -->
            <Card>
                <CardHeader>
                    <CardTitle>Decision Summary</CardTitle>
                    <CardDescription>How users responded to recommendations</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-8">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                                <CheckCircle class="h-6 w-6 text-green-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ metrics.accepted }}</p>
                                <p class="text-sm text-muted-foreground">Accepted</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center">
                                <AlertCircle class="h-6 w-6 text-yellow-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ metrics.adjusted }}</p>
                                <p class="text-sm text-muted-foreground">Adjusted</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                                <AlertTriangle class="h-6 w-6 text-red-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ metrics.rejected }}</p>
                                <p class="text-sm text-muted-foreground">Rejected</p>
                            </div>
                        </div>
                        <div class="flex-1" />
                        <router-link :to="AnalyticsController.missedPredictions().url">
                            <Badge variant="outline" class="cursor-pointer hover:bg-muted">
                                Review {{ metrics.adjusted + metrics.rejected }} missed predictions
                            </Badge>
                        </router-link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle,
    XCircle,
    AlertCircle,
    Github,
    Bot,
    Database,
    Plus,
    Play,
    GitBranch,
    Clock,
    TrendingUp,
    Target,
    Sparkles,
    ArrowRight,
} from 'lucide-vue-next';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import { formatDistanceToNow } from 'date-fns';

interface Repository {
    id: number;
    name: string;
    full_name: string;
    releases_count: number;
    last_synced_at: string | null;
}

interface Release {
    id: number;
    version: string | null;
    recommended_version: string;
    recommended_type: string;
    status: string;
    created_at: string;
    repository: {
        id: number;
        name: string;
        full_name: string;
    };
}

interface SystemStatus {
    github: {
        connected: boolean;
        username: string | null;
    };
    ai_provider: {
        configured: boolean;
        provider: string | null;
    };
    database: {
        status: string;
    };
}

interface AccuracySummary {
    acceptance_rate: number | null;
    breaking_detection_rate: number | null;
    total_releases: number;
    has_sufficient_data: boolean;
}

defineProps<{
    systemStatus: SystemStatus;
    repositories: Repository[];
    recentReleases: Release[];
    accuracySummary: AccuracySummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'accepted':
            return 'text-green-500';
        case 'adjusted':
            return 'text-yellow-500';
        case 'rejected':
            return 'text-red-500';
        default:
            return 'text-muted-foreground';
    }
};

const getTypeVariant = (type: string): 'destructive' | 'default' | 'secondary' | 'outline' => {
    switch (type) {
        case 'MAJOR':
            return 'destructive';
        case 'MINOR':
            return 'default';
        case 'PATCH':
            return 'secondary';
        default:
            return 'outline';
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- System Status Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- GitHub Status -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">GitHub</CardTitle>
                        <Github class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <CheckCircle v-if="systemStatus.github.connected" class="h-5 w-5 text-green-500" />
                            <XCircle v-else class="h-5 w-5 text-red-500" />
                            <span class="text-lg font-semibold">
                                {{ systemStatus.github.connected ? 'Connected' : 'Not Connected' }}
                            </span>
                        </div>
                        <p v-if="systemStatus.github.username" class="text-sm text-muted-foreground mt-1">
                            @{{ systemStatus.github.username }}
                        </p>
                        <Link v-else href="/auth/github" class="text-sm text-primary hover:underline mt-1 block">
                            Connect GitHub
                        </Link>
                    </CardContent>
                </Card>

                <!-- AI Provider Status -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">AI Provider</CardTitle>
                        <Bot class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <CheckCircle v-if="systemStatus.ai_provider.configured" class="h-5 w-5 text-green-500" />
                            <AlertCircle v-else class="h-5 w-5 text-yellow-500" />
                            <span class="text-lg font-semibold">
                                {{ systemStatus.ai_provider.configured ? 'Configured' : 'Not Configured' }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ systemStatus.ai_provider.provider || 'Configure in Settings' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Database Status -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Database</CardTitle>
                        <Database class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-green-500" />
                            <span class="text-lg font-semibold capitalize">
                                {{ systemStatus.database.status }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">SQLite</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Getting Started Banner (for new users) -->
            <Card v-if="recentReleases.length === 0 && repositories.length > 0" class="border-primary/20 bg-primary/5">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-primary" />
                        <CardTitle>Welcome to LaraLedger!</CardTitle>
                    </div>
                    <CardDescription>
                        LaraLedger helps you analyze your commits and suggest semantic version bumps (MAJOR, MINOR, PATCH) for your releases.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold">1</div>
                                <div>
                                    <p class="font-medium">Select a Repository</p>
                                    <p class="text-sm text-muted-foreground">Choose from your connected GitHub repositories</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold">2</div>
                                <div>
                                    <p class="font-medium">Analyze Commits</p>
                                    <p class="text-sm text-muted-foreground">We'll examine commits since your last release</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold">3</div>
                                <div>
                                    <p class="font-medium">Get Recommendations</p>
                                    <p class="text-sm text-muted-foreground">Review suggested version bump and release notes</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-center pt-2">
                            <Link :href="AnalysisController.create(repositories[0].id).url">
                                <Button>
                                    <Play class="mr-2 h-4 w-4" />
                                    Analyze Your First Release
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions & Accuracy Summary -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Quick Actions -->
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>Common tasks for managing releases</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button :disabled="repositories.length === 0">
                            <Play class="mr-2 h-4 w-4" />
                            New Release Analysis
                        </Button>
                        <Link :href="RepositoryController.create().url">
                            <Button variant="outline">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Repository
                            </Button>
                        </Link>
                        <Link :href="RepositoryController.index().url">
                            <Button variant="outline">
                                <GitBranch class="mr-2 h-4 w-4" />
                                View All Releases
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Accuracy Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Accuracy Summary</CardTitle>
                        <CardDescription>Last 30 days performance</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="accuracySummary.has_sufficient_data" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">Acceptance Rate</span>
                                </div>
                                <span class="text-2xl font-bold">{{ accuracySummary.acceptance_rate }}%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Target class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">Breaking Detection</span>
                                </div>
                                <span class="text-2xl font-bold">
                                    {{ accuracySummary.breaking_detection_rate !== null ? `${accuracySummary.breaking_detection_rate}%` : '—' }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Based on {{ accuracySummary.total_releases }} releases
                            </p>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                            <AlertCircle class="h-8 w-8 text-muted-foreground mb-2" />
                            <p class="text-sm text-muted-foreground">
                                Insufficient data. Analyze at least 5 releases to see accuracy metrics.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Repositories & Recent Activity -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Repository Quick List -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Repositories</CardTitle>
                            <CardDescription>Your connected repositories</CardDescription>
                        </div>
                        <Link :href="RepositoryController.index().url">
                            <Button variant="ghost" size="sm">View All</Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div v-if="repositories.length > 0" class="space-y-3">
                            <Link
                                v-for="repo in repositories"
                                :key="repo.id"
                                :href="RepositoryController.show(repo.id).url"
                                class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                            >
                                <div>
                                    <p class="font-medium">{{ repo.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ repo.releases_count }} releases
                                    </p>
                                </div>
                                <p v-if="repo.last_synced_at" class="text-xs text-muted-foreground">
                                    {{ formatDistanceToNow(new Date(repo.last_synced_at), { addSuffix: true }) }}
                                </p>
                            </Link>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                            <GitBranch class="h-8 w-8 text-muted-foreground mb-2" />
                            <p class="text-sm text-muted-foreground mb-3">No repositories connected</p>
                            <Link :href="RepositoryController.create().url">
                                <Button size="sm">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Repository
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Activity -->
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                        <CardDescription>Latest release analyses</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentReleases.length > 0" class="space-y-3">
                            <div
                                v-for="release in recentReleases"
                                :key="release.id"
                                class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'h-2 w-2 rounded-full',
                                            release.status === 'accepted' ? 'bg-green-500' :
                                            release.status === 'adjusted' ? 'bg-yellow-500' :
                                            release.status === 'rejected' ? 'bg-red-500' : 'bg-muted-foreground'
                                        ]"
                                    />
                                    <div>
                                        <p class="font-medium text-sm">{{ release.repository.name }}</p>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs">{{ release.recommended_version }}</span>
                                            <Badge :variant="getTypeVariant(release.recommended_type)" class="text-xs">
                                                {{ release.recommended_type }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDistanceToNow(new Date(release.created_at), { addSuffix: true }) }}
                                </p>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                            <Clock class="h-8 w-8 text-muted-foreground mb-2" />
                            <p class="text-sm text-muted-foreground">
                                No recent activity. Start by analyzing a release.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

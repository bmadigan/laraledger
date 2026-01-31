<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    GitBranch,
    RefreshCw,
    Settings,
    ExternalLink,
    Play,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Tag,
} from 'lucide-vue-next';
import { formatDistanceToNow, format } from 'date-fns';

interface Release {
    id: number;
    version: string | null;
    recommended_version: string;
    recommended_type: string;
    final_version: string | null;
    final_type: string | null;
    confidence: number;
    status: string;
    created_at: string;
}

interface GitTag {
    id: number;
    name: string;
    sha: string;
    created_at_github: string | null;
}

interface Repository {
    id: number;
    github_id: number;
    name: string;
    full_name: string;
    description: string | null;
    default_branch: string;
    tag_pattern: string;
    ignored_paths: string[] | null;
    is_private: boolean;
    last_synced_at: string | null;
    releases_count: number;
    tags_count: number;
    releases: Release[];
}

const props = defineProps<{
    repository: Repository;
    tags: GitTag[];
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
    }

    return items;
});

const syncRepository = () => {
    router.post(`/repositories/${props.repository.id}/sync`);
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'accepted':
            return CheckCircle;
        case 'adjusted':
            return AlertCircle;
        case 'rejected':
            return XCircle;
        default:
            return Clock;
    }
};

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

const getTypeColor = (type: string) => {
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
    <Head :title="repository.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ repository.name }}</h1>
                        <Badge v-if="repository.is_private" variant="secondary">Private</Badge>
                        <a
                            :href="`https://github.com/${repository.full_name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </a>
                    </div>
                    <p class="text-muted-foreground">{{ repository.full_name }}</p>
                    <p v-if="repository.description" class="text-sm text-muted-foreground">
                        {{ repository.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="syncRepository">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Sync
                    </Button>
                    <Link :href="`/repositories/${repository.id}/edit`">
                        <Button variant="outline">
                            <Settings class="mr-2 h-4 w-4" />
                            Settings
                        </Button>
                    </Link>
                    <Link :href="`/repositories/${repository.id}/analyze`">
                        <Button>
                            <Play class="mr-2 h-4 w-4" />
                            New Analysis
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Releases</CardDescription>
                        <CardTitle class="text-3xl">{{ repository.releases_count }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Default Branch</CardDescription>
                        <CardTitle class="flex items-center gap-2">
                            <GitBranch class="h-5 w-5" />
                            {{ repository.default_branch }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Tag Pattern</CardDescription>
                        <CardTitle class="font-mono text-lg">{{ repository.tag_pattern }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Last Synced</CardDescription>
                        <CardTitle class="text-lg">
                            {{ repository.last_synced_at
                                ? formatDistanceToNow(new Date(repository.last_synced_at), { addSuffix: true })
                                : 'Never' }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- GitHub Tags -->
            <Card v-if="tags && tags.length > 0">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Tag class="h-5 w-5" />
                                GitHub Tags
                            </CardTitle>
                            <CardDescription>
                                {{ tags.length }} tags synced from GitHub
                            </CardDescription>
                        </div>
                        <Link :href="`/repositories/${repository.id}/analyze`">
                            <Button variant="outline" size="sm">
                                <Play class="mr-2 h-4 w-4" />
                                Analyze Tags
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="tag in tags.slice(0, 20)"
                            :key="tag.id"
                            variant="outline"
                            class="font-mono text-xs"
                        >
                            {{ tag.name }}
                        </Badge>
                        <span v-if="tags.length > 20" class="text-sm text-muted-foreground self-center">
                            +{{ tags.length - 20 }} more
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Releases -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Analyses</CardTitle>
                    <CardDescription>
                        The last 10 release analyses for this repository
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="repository.releases.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
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
                                    v-for="release in repository.releases"
                                    :key="release.id"
                                    class="border-b last:border-0 hover:bg-muted/50 cursor-pointer"
                                    @click="router.visit(`/repositories/${repository.id}/releases/${release.id}`)"
                                >
                                    <td class="px-4 py-3 font-mono text-sm">
                                        {{ release.version || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm">{{ release.recommended_version }}</span>
                                            <Badge :variant="getTypeColor(release.recommended_type)">
                                                {{ release.recommended_type }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="release.final_version" class="flex items-center gap-2">
                                            <span class="font-mono text-sm">{{ release.final_version }}</span>
                                            <Badge v-if="release.final_type" :variant="getTypeColor(release.final_type)">
                                                {{ release.final_type }}
                                            </Badge>
                                        </div>
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
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <Clock class="h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-lg font-semibold">No releases analyzed yet</h3>
                        <p class="text-muted-foreground mb-4">
                            Start a new analysis to see your release history.
                        </p>
                        <Link :href="`/repositories/${repository.id}/analyze`">
                            <Button>
                                <Play class="mr-2 h-4 w-4" />
                                Start First Analysis
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

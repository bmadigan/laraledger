<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, GitBranch, RefreshCw, Settings, Trash2, ExternalLink } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';

interface Repository {
    id: number;
    github_id: number;
    name: string;
    full_name: string;
    description: string | null;
    default_branch: string;
    is_private: boolean;
    last_synced_at: string | null;
    releases_count: number;
}

defineProps<{
    repositories: Repository[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Repositories',
        href: RepositoryController.index().url,
    },
];

const syncRepository = (repository: Repository) => {
    router.post(RepositoryController.sync(repository.id).url);
};

const deleteRepository = (repository: Repository) => {
    if (confirm(`Are you sure you want to disconnect "${repository.full_name}"? This will delete all release history for this repository.`)) {
        router.delete(RepositoryController.destroy(repository.id).url);
    }
};
</script>

<template>
    <Head title="Repositories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Repositories</h1>
                    <p class="text-muted-foreground">Manage your connected GitHub repositories</p>
                </div>
                <Link :href="RepositoryController.create().url">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Add Repository
                    </Button>
                </Link>
            </div>

            <!-- Repository List -->
            <div v-if="repositories.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="repo in repositories" :key="repo.id" class="flex flex-col">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <CardTitle class="flex items-center gap-2">
                                    <Link :href="RepositoryController.show(repo.id).url" class="hover:underline">
                                        {{ repo.name }}
                                    </Link>
                                    <Badge v-if="repo.is_private" variant="secondary">Private</Badge>
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    {{ repo.full_name }}
                                </CardDescription>
                            </div>
                            <a
                                :href="`https://github.com/${repo.full_name}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-muted-foreground hover:text-foreground"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </a>
                        </div>
                    </CardHeader>
                    <CardContent class="flex-1">
                        <p v-if="repo.description" class="text-sm text-muted-foreground line-clamp-2 mb-4">
                            {{ repo.description }}
                        </p>
                        <div class="flex items-center gap-4 text-sm text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <GitBranch class="h-4 w-4" />
                                {{ repo.default_branch }}
                            </div>
                            <div>
                                {{ repo.releases_count }} releases
                            </div>
                        </div>
                        <p v-if="repo.last_synced_at" class="text-xs text-muted-foreground mt-2">
                            Last synced {{ formatDistanceToNow(new Date(repo.last_synced_at), { addSuffix: true }) }}
                        </p>
                    </CardContent>
                    <div class="border-t p-3 flex gap-2">
                        <Button variant="ghost" size="sm" @click="syncRepository(repo)">
                            <RefreshCw class="h-4 w-4" />
                        </Button>
                        <Link :href="RepositoryController.edit(repo.id).url">
                            <Button variant="ghost" size="sm">
                                <Settings class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="ghost" size="sm" class="text-destructive" @click="deleteRepository(repo)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else class="flex flex-col items-center justify-center p-12 text-center">
                <GitBranch class="h-12 w-12 text-muted-foreground mb-4" />
                <h3 class="text-lg font-semibold">No repositories connected</h3>
                <p class="text-muted-foreground mb-6">
                    Connect a GitHub repository to start analyzing releases.
                </p>
                <Link :href="RepositoryController.create().url">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Connect Your First Repository
                    </Button>
                </Link>
            </Card>
        </div>
    </AppLayout>
</template>

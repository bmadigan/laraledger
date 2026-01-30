<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { GitBranch, Lock, Globe, Search, Check, Plus, Loader2 } from 'lucide-vue-next';

interface GitHubRepository {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    private: boolean;
    default_branch: string;
}

const props = defineProps<{
    availableRepositories: GitHubRepository[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Repositories',
        href: RepositoryController.index().url,
    },
    {
        title: 'Connect Repository',
        href: RepositoryController.create().url,
    },
];

const searchQuery = ref('');
const selectedRepo = ref<GitHubRepository | null>(null);
const isSubmitting = ref(false);

// Public repository state
const publicRepoName = ref('');
const isAddingPublicRepo = ref(false);

const filteredRepositories = computed(() => {
    if (!searchQuery.value) {
        return props.availableRepositories;
    }
    const query = searchQuery.value.toLowerCase();
    return props.availableRepositories.filter(
        (repo) =>
            repo.name.toLowerCase().includes(query) ||
            repo.full_name.toLowerCase().includes(query) ||
            (repo.description && repo.description.toLowerCase().includes(query))
    );
});

const selectRepository = (repo: GitHubRepository) => {
    selectedRepo.value = repo;
};

const connectRepository = () => {
    if (!selectedRepo.value) return;

    isSubmitting.value = true;
    router.post(RepositoryController.store().url, {
        github_id: selectedRepo.value.id,
        name: selectedRepo.value.name,
        full_name: selectedRepo.value.full_name,
        description: selectedRepo.value.description,
        default_branch: selectedRepo.value.default_branch,
        is_private: selectedRepo.value.private,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const addPublicRepository = () => {
    if (!publicRepoName.value.trim() || !publicRepoName.value.includes('/')) return;

    isAddingPublicRepo.value = true;
    router.post(RepositoryController.store().url, {
        full_name: publicRepoName.value.trim(),
    }, {
        onFinish: () => {
            isAddingPublicRepo.value = false;
        },
        onError: () => {
            // Keep the input value so user can correct it
        },
    });
};
</script>

<template>
    <Head title="Connect Repository" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-4xl mx-auto">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Connect Repository</h1>
                <p class="text-muted-foreground">
                    Select a GitHub repository to connect to LaraLedger
                </p>
            </div>

            <!-- Add Public Repository -->
            <Card class="border-dashed">
                <CardHeader class="py-4">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Globe class="h-4 w-4" />
                        Add Public Repository
                    </CardTitle>
                    <CardDescription>
                        Add any public GitHub repository by entering its full name
                    </CardDescription>
                </CardHeader>
                <CardContent class="pt-0">
                    <div class="flex gap-2">
                        <Input
                            v-model="publicRepoName"
                            placeholder="owner/repository (e.g., laravel/framework)"
                            class="flex-1"
                            @keyup.enter="addPublicRepository"
                        />
                        <Button
                            :disabled="!publicRepoName.includes('/') || isAddingPublicRepo"
                            @click="addPublicRepository"
                        >
                            <Loader2 v-if="isAddingPublicRepo" class="mr-2 h-4 w-4 animate-spin" />
                            <Plus v-else class="mr-2 h-4 w-4" />
                            {{ isAddingPublicRepo ? 'Adding...' : 'Add Repository' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">Or select from your repositories</span>
                </div>
            </div>

            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search repositories..."
                    class="pl-10"
                />
            </div>

            <!-- Repository List -->
            <div v-if="filteredRepositories.length > 0" class="space-y-2">
                <Card
                    v-for="repo in filteredRepositories"
                    :key="repo.id"
                    :class="[
                        'cursor-pointer transition-colors',
                        selectedRepo?.id === repo.id
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-muted/50'
                    ]"
                    @click="selectRepository(repo)"
                >
                    <CardHeader class="py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="[
                                        'flex h-5 w-5 items-center justify-center rounded-full border',
                                        selectedRepo?.id === repo.id
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-muted-foreground/30'
                                    ]"
                                >
                                    <Check v-if="selectedRepo?.id === repo.id" class="h-3 w-3" />
                                </div>
                                <div>
                                    <CardTitle class="text-base flex items-center gap-2">
                                        {{ repo.full_name }}
                                        <Badge v-if="repo.private" variant="secondary" class="text-xs">
                                            <Lock class="h-3 w-3 mr-1" />
                                            Private
                                        </Badge>
                                        <Badge v-else variant="outline" class="text-xs">
                                            <Globe class="h-3 w-3 mr-1" />
                                            Public
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription v-if="repo.description" class="text-xs mt-1">
                                        {{ repo.description }}
                                    </CardDescription>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                <GitBranch class="h-4 w-4" />
                                {{ repo.default_branch }}
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else class="flex flex-col items-center justify-center p-12 text-center">
                <GitBranch class="h-12 w-12 text-muted-foreground mb-4" />
                <h3 class="text-lg font-semibold">No repositories found</h3>
                <p class="text-muted-foreground">
                    {{ searchQuery ? 'Try a different search term' : 'All your repositories are already connected' }}
                </p>
            </Card>

            <!-- Action Button -->
            <div class="flex justify-end border-t pt-4">
                <Button
                    :disabled="!selectedRepo || isSubmitting"
                    @click="connectRepository"
                >
                    {{ isSubmitting ? 'Connecting...' : 'Connect Repository' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>

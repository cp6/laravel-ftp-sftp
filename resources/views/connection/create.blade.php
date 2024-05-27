<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create a connection') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('connection.store') }}">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 p-4">
                        <div class="mb-4 md:col-span-6 col-span-12">
                            <label for="host" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Host
                                or address</label>
                            <input type="text" name="host" id="host"
                                   class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                   required>
                        </div>

                        <div class="mb-4 md:col-span-2 col-span-12">
                            <label for="port"
                                   class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Port</label>
                            <input type="number" name="port" id="port" min="1" max="999999" value="22"
                                   class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                   required>
                        </div>

                        <div class="mb-4 md:col-span-6 col-span-12">
                            <label for="username" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Username</label>
                            <input type="text" name="username" id="username" maxlength="64"
                                   class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                   required>
                        </div>

                        <div class="mb-4 md:col-span-6 col-span-12">
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Password</label>
                            <input type="password" name="password" id="password"
                                   class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        </div>

                        <div class="mb-4 md:col-span-2 col-span-12">
                            <label for="timeout" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Timeout</label>
                            <input type="number" name="timeout" id="timeout" min="1" max="999"
                                   class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                   value="6">
                        </div>

                        <div class="mb-4 mt-8 md:col-span-6 col-span-12">
                            <div class="flex items-center mb-4">
                                <fieldset>
                                    <input type="hidden" value="0" name="log_actions">
                                    <input checked id="log_actions" type="checkbox" value=""
                                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="log_actions"
                                           class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Log actions</label>
                                </fieldset>
                            </div>
                        </div>

                        <div class="mb-4 md:col-span-12 col-span-12">
                            <label for="key"
                                   class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Key</label>
                            <textarea name="key" id="key"
                                      class="form-textarea mt-1 block w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                      rows="3"></textarea>
                        </div>
                    </div>

                    <div class="mb-4 p-4">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

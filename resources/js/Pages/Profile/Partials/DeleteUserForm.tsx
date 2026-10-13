import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import { Button, Card } from '@/Components/Panel/ui';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <Card title="Excluir conta">
            <p className="mb-4 text-sm text-gray-600">
                Ao excluir a conta, todos os dados dela são apagados em definitivo. Antes de continuar, guarde o que
                precisar.
            </p>

            <Button variant="danger" onClick={() => setConfirmingUserDeletion(true)}>
                Excluir minha conta
            </Button>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900">Excluir a conta?</h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Esta ação não pode ser desfeita. Digite a sua senha para confirmar.
                    </p>

                    <div className="mt-5">
                        <InputLabel htmlFor="delete_password" value="Senha" className="sr-only" />

                        <TextInput
                            id="delete_password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="block w-full"
                            isFocused
                            placeholder="Senha"
                        />

                        <InputError message={errors.password} className="mt-1" />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={closeModal}>Cancelar</SecondaryButton>

                        <DangerButton disabled={processing}>Excluir conta</DangerButton>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}

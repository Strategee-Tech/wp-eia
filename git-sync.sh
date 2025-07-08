#!/bin/bash

# Este script automatiza el proceso de pull, add, commit y push a la rama 'main'.

# --- Configuración (puedes ajustarla) ---
REMOTE_NAME="origin" # El nombre de tu remoto (generalmente 'origin')
BRANCH_NAME="main"   # La rama a la que quieres hacer push

# --- Procesamiento del mensaje de commit ---
COMMIT_MESSAGE=$1 # El primer argumento pasado al script será el mensaje del commit

# Si no se proporciona un mensaje de commit, usa uno por defecto
if [ -z "$COMMIT_MESSAGE" ]; then
  COMMIT_MESSAGE="feature/images"
  echo "ADVERTENCIA: No se proporcionó un mensaje de commit. Usando el mensaje por defecto: \"$COMMIT_MESSAGE\""
fi

echo "--- Iniciando sincronización Git ---"

# 1. git pull: Obtener los últimos cambios del remoto
echo "1. Ejecutando 'git pull $REMOTE_NAME $BRANCH_NAME'..."
git pull $REMOTE_NAME $BRANCH_NAME

# Verificar si el pull fue exitoso o hubo conflictos
if [ $? -ne 0 ]; then
  echo "ERROR: 'git pull' falló o hay conflictos que deben resolverse manualmente."
  echo "Por favor, resuelve los conflictos y luego intenta ejecutar el script de nuevo."
  exit 1 # Sale del script con un código de error
fi
echo "Pull completado."

# 2. git add .: Preparar todos los cambios para el commit
echo "2. Ejecutando 'git add .'..."
git add .
echo "Cambios añadidos al área de preparación."

# 3. git commit: Crear un nuevo commit con el mensaje proporcionado
echo "3. Ejecutando 'git commit -m \"$COMMIT_MESSAGE\"'..."
git commit -m "$COMMIT_MESSAGE"

# Verificar si el commit fue exitoso (puede fallar si no hay cambios para commitear)
if [ $? -ne 0 ]; then
  echo "ADVERTENCIA: 'git commit' falló (posiblemente no hay cambios para commitear). Intentando push de todos modos si pull fue exitoso."
  # Si no hay cambios para commitear, git commit devuelve un error, pero queremos continuar si solo se pulió.
fi
echo "Commit creado."

# 4. git push: Enviar los cambios al repositorio remoto
echo "4. Ejecutando 'git push $REMOTE_NAME $BRANCH_NAME'..."
git push $REMOTE_NAME $BRANCH_NAME

# Verificar si el push fue exitoso
if [ $? -ne 0 ]; then
  echo "ERROR: 'git push' falló. Por favor, revisa tus credenciales o el estado de la rama remota."
  exit 1 # Sale del script con un código de error
fi
echo "--- Sincronización Git completada con éxito. ---"

exit 0 # Sale del script con éxito